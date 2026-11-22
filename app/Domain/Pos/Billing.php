<?php

namespace App\Domain\Pos;

use App\Models\PosBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Billing
{
    public function execute(Counter $counter, array $input): int
    {
        $key = strtolower($input['key']);
        unset($input['key']);
        $fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

        return $counter->locked(function (Counter $counter) use ($key, $input, $fingerprint) {
            $previous = $counter->rows('pos_requests')->where('request_key', $key)->first();
            if ($previous) {
                abort_unless((int) $previous->staff_id === $counter->staff->id && hash_equals($previous->fingerprint, $fingerprint), 409, 'This request key was already used for another operation.');

                return (int) $previous->bill_id;
            }

            $bill = match ($input['action']) {
                'order' => $this->order($counter, $input),
                'settle' => $this->settle($counter, $input),
                'cancel', 'void', 'refund' => $this->adjust($counter, $input),
            };
            DB::table('pos_requests')->insert($counter->scope() + [
                'request_key' => $key, 'fingerprint' => $fingerprint, 'bill_id' => $bill,
                'staff_id' => $counter->staff->id, 'created_at' => now(),
            ]);

            return $bill;
        });
    }

    public function bill(Counter $counter, int|string $id): PosBill
    {
        return PosBill::query()->where($counter->scope())->findOrFail($id);
    }

    public function totals(Counter $counter, int $bill): array
    {
        $columns = ['subtotal', 'discount', 'vat', 'sd', 'service', 'total'];
        $select = implode(', ', array_map(fn ($column) => "COALESCE(SUM({$column}_minor), 0) as {$column}_minor", $columns));

        return array_map('intval', (array) $counter->rows('pos_orders')
            ->where('bill_id', $bill)->where('status', 'accepted')->selectRaw($select)->first());
    }

    private function order(Counter $counter, array $input): int
    {
        $mode = $counter->branch->pos_billing_mode;
        abort_unless($input['mode'] === $mode, 409, 'The branch billing mode changed. Reload the counter.');
        $bill = isset($input['bill_id']) ? $this->bill($counter, $input['bill_id']) : null;
        abort_if($bill && ($bill->status !== 'open' || $bill->mode !== 'pay_later' || $mode !== 'pay_later'), 409, 'This visit is no longer open.');

        $lines = $input['lines'];
        $menu = Menu::available($counter)->whereIn('p.id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $snapshots = [];
        $subtotal = 0;
        foreach ($lines as $line) {
            $product = $menu->get($line['product_id']);
            abort_unless($product && (int) $product->price_minor === (int) $line['unit_price_minor'], 409, 'The menu changed. Refresh it, then remove and re-add any changed items.');
            $subtotal += (int) $product->price_minor * (int) $line['quantity'];
            $snapshots[] = [
                'product_id' => $product->id, 'product_name' => $product->name,
                'unit_price_minor' => $product->price_minor, 'quantity' => $line['quantity'], 'note' => $line['note'] ?? null,
            ];
        }

        $amounts = ['subtotal_minor' => $subtotal];
        foreach (['discount', 'vat', 'sd', 'service'] as $key) {
            $amounts[$key.'_minor'] = Money::minor($input[$key] ?? '0');
        }
        abort_if(array_sum($amounts) !== $subtotal && ! $counter->manages(), 403);
        if ($amounts['discount_minor'] > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'The discount cannot exceed the subtotal.']);
        }

        $total = array_sum($amounts) - 2 * $amounts['discount_minor'];
        abort_unless($total === Money::minor($input['expected_total']), 409, 'The order total changed. Review the amount before confirming.');
        $previousTotal = $bill ? $this->totals($counter, $bill->id)['total_minor'] : 0;
        abort_if($previousTotal + $total > Money::MAX_MINOR, 422, 'This addition would exceed the maximum bill amount.');
        if ($mode === 'pay_first') {
            $this->paymentDetails($counter, $input, $total);
        }

        $bill ??= PosBill::create($counter->scope() + [
            'mode' => $mode, 'status' => 'open',
            'simulated' => $counter->staff->restaurant->tenant_type !== 'production',
            'visit_label' => $mode === 'pay_later' ? ($input['visit_label'] ?? null) : null,
        ]);

        $order = DB::table('pos_orders')->insertGetId($counter->scope() + $amounts + [
            'bill_id' => $bill->id, 'number' => (string) Str::ulid(),
            'collection_token' => $mode === 'pay_first' ? ($input['collection_token'] ?? null) : null,
            'status' => 'accepted', 'preparation_status' => 'queued',
            'staff_id' => $counter->staff->id, 'total_minor' => $total,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pos_order_lines')->insert(array_map(
            fn ($line) => $counter->scope() + ['order_id' => $order] + $line,
            $snapshots,
        ));

        if ($mode === 'pay_first') {
            $this->recordSettlement($counter, $bill, $input, $total);
        }

        return $bill->id;
    }

    private function settle(Counter $counter, array $input): int
    {
        $bill = $this->bill($counter, $input['bill_id']);
        abort_unless($bill->mode === 'pay_later' && $bill->status === 'open', 409, 'This bill is no longer open.');
        abort_unless($counter->rows('pos_orders')->where('bill_id', $bill->id)->where('status', 'accepted')->exists(), 409, 'The bill has no active orders.');
        $total = $this->totals($counter, $bill->id)['total_minor'];
        $this->paymentDetails($counter, $input, $total);
        $this->recordSettlement($counter, $bill, $input, $total);

        return $bill->id;
    }

    private function paymentDetails(Counter $counter, array $input, int $total): void
    {
        $method = $input['method'] ?? null;
        if (! ($input['confirmed'] ?? false) || ! in_array($method, $counter->methods(), true) ||
            ($method !== 'cash' && blank($input['reference'] ?? null))) {
            throw ValidationException::withMessages(['payment' => 'Explicitly confirm payment with an enabled method and enter a reference for non-cash payments.']);
        }
        abort_unless(isset($input['paid_amount']) && Money::minor($input['paid_amount']) === $total, 409, 'The payment amount must match the current bill total.');
    }

    private function recordSettlement(Counter $counter, PosBill $bill, array $input, int $total): void
    {
        $this->payment($counter, $bill, 'payment', $input['method'], $total, $input['reference'] ?? null);
        $bill->update(['status' => 'settled', 'settled_total_minor' => $total, 'settled_at' => now()]);
        $counter->audit('payment.confirmed', ['bill_id' => $bill->id, 'method' => $input['method'], 'amount_minor' => $total]);
    }

    private function adjust(Counter $counter, array $input): int
    {
        abort_unless($counter->manages(), 403);
        $bill = $this->bill($counter, $input['bill_id']);
        $action = $input['action'];
        abort_unless($bill->status === ($action === 'refund' ? 'settled' : 'open'), 409, 'This adjustment is no longer available.');
        $orders = $counter->rows('pos_orders')->where('bill_id', $bill->id);

        $details = ['bill_id' => $bill->id, 'reason' => $input['reason']];

        if ($action === 'refund') {
            $paid = $counter->rows('pos_payments')->where('bill_id', $bill->id)->where('kind', 'payment')->firstOrFail();
            if (! ($input['confirmed'] ?? false) || ($paid->method !== 'cash' && blank($input['reference'] ?? null))) {
                throw ValidationException::withMessages(['refund' => 'Confirm the full refund and enter a reference for non-cash refunds.']);
            }
            $this->payment($counter, $bill, 'refund', $paid->method, $paid->amount_minor, $input['reference'] ?? null);
            $bill->update(['status' => 'refunded']);
            $orders->where('preparation_status', 'queued')->update(['preparation_status' => 'cancelled', 'updated_at' => now()]);
            $details['amount_minor'] = $paid->amount_minor;
        } else {
            if ($action === 'cancel') {
                $order = (clone $orders)->where('id', $input['order_id'])->firstOrFail();
                abort_unless($order->status === 'accepted', 409, 'This order has already been cancelled.');
                $orders->where('id', $order->id);
                $details['order_id'] = $order->id;
            } else {
                $bill->update(['status' => 'voided']);
            }
            $orders->where('status', 'accepted')->update([
                'status' => 'cancelled', 'preparation_status' => 'cancelled', 'updated_at' => now(),
            ]);
        }

        $counter->audit('bill.'.$action, $details);

        return $bill->id;
    }

    private function payment(Counter $counter, PosBill $bill, string $kind, string $method, int $total, ?string $reference): void
    {
        DB::table('pos_payments')->insert($counter->scope() + [
            'bill_id' => $bill->id, 'kind' => $kind, 'method' => $method, 'amount_minor' => $total,
            'reference' => $reference, 'staff_id' => $counter->staff->id, 'created_at' => now(),
        ]);
    }
}
