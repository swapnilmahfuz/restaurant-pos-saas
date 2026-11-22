<?php

namespace App\Http\Controllers;

use App\Domain\Pos\Billing;
use App\Domain\Pos\Counter;
use App\Domain\Pos\Menu;
use App\Domain\Pos\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CounterController
{
    public function index(Request $request, string $branch, Billing $billing): View
    {
        $counter = Counter::open($request->user('staff'), $branch);
        $input = $request->validate(['bill' => ['sometimes', 'integer', 'min:1']]);
        $visit = isset($input['bill']) ? $billing->bill($counter, $input['bill']) : null;
        abort_if($visit && ($visit->mode !== 'pay_later' || $visit->status !== 'open'), 409, 'Only open Pay Later visits accept additional orders.');

        return view('pos.counter', [
            'counter' => $counter, 'visit' => $visit, 'methods' => Counter::METHODS,
        ]);
    }

    public function menu(Request $request, string $branch): JsonResponse
    {
        $counter = Counter::open($request->user('staff'), $branch);
        $input = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'integer', 'min:1']]);
        $menu = Menu::available($counter);
        $categories = (clone $menu)->reorder()->select('c.id', 'c.name')->distinct()->orderBy('c.name')->get();
        if (isset($input['q'])) {
            $literal = strtr($input['q'], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $menu->whereRaw("p.name LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        $menu->when($input['category'] ?? null, fn ($query, $category) => $query->where('p.category_id', $category));
        $products = $menu->orderBy('p.name')->orderBy('p.id')->simplePaginate(60);

        return response()->json(['categories' => $categories, 'products' => $products->items(), 'more' => $products->hasMorePages()]);
    }

    public function history(Request $request, string $branch): View
    {
        $counter = Counter::open($request->user('staff'), $branch);
        $open = $request->query('status') === 'open';

        return view('pos.history', [
            'counter' => $counter, 'open' => $open,
            'bills' => $counter->rows('pos_bills')->when($open, fn ($query) => $query->where('status', 'open'))
                ->latest('id')->simplePaginate(25)->withQueryString(),
        ]);
    }

    public function show(Request $request, string $branch, string $bill, Billing $billing): View
    {
        $counter = Counter::open($request->user('staff'), $branch);
        $record = $billing->bill($counter, $bill);
        $orders = $counter->rows('pos_orders')->where('bill_id', $record->id)->orderBy('id')->get();

        return view('pos.bill', [
            'counter' => $counter, 'bill' => $record, 'orders' => $orders,
            'lines' => $counter->rows('pos_order_lines')->whereIn('order_id', $orders->pluck('id'))->orderBy('id')->get()->groupBy('order_id'),
            'payments' => $counter->rows('pos_payments')->where('bill_id', $record->id)->orderBy('id')->get(),
            'totals' => $billing->totals($counter, $record->id), 'methods' => Counter::METHODS,
        ]);
    }

    public function status(Request $request, string $branch, string $key): JsonResponse
    {
        $counter = Counter::open($request->user('staff'), $branch);
        $record = $counter->rows('pos_requests')->where('request_key', strtolower($key))->where('staff_id', $counter->staff->id)->firstOrFail();

        return $this->result($branch, $record->bill_id);
    }

    public function submit(Request $request, string $branch, Billing $billing): JsonResponse
    {
        $counter = Counter::open($request->user('staff'), $branch);
        $request->validate(['action' => ['required', Rule::in(['order', 'settle', 'cancel', 'void', 'refund'])]]);
        $action = $request->input('action');
        $money = ['nullable', 'string', Money::INPUT];
        $rules = ['action' => ['required', 'string'], 'key' => ['required', 'uuid']];
        if ($action === 'order') {
            $rules += [
                'mode' => ['required', Rule::in(['pay_first', 'pay_later'])],
                'bill_id' => ['nullable', 'integer', 'min:1'],
                'visit_label' => ['nullable', 'string', 'max:80'], 'collection_token' => ['nullable', 'string', 'max:40'],
                'lines' => ['required', 'array', 'list', 'max:50'],
                'lines.*' => ['required', 'array:product_id,quantity,note,unit_price_minor'],
                'lines.*.product_id' => ['required', 'integer', 'min:1'],
                'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
                'lines.*.unit_price_minor' => ['required', 'integer', 'min:0', 'max:'.Money::MAX_MINOR],
                'lines.*.note' => ['nullable', 'string', 'max:250'],
                'discount' => $money, 'vat' => $money, 'sd' => $money, 'service' => $money,
                'expected_total' => ['required', 'string', Money::INPUT],
            ];
        } else {
            $rules['bill_id'] = ['required', 'integer', 'min:1'];
        }
        if (in_array($action, ['order', 'settle', 'refund'], true)) {
            $rules += [
                'method' => ['nullable', Rule::in(array_keys(Counter::METHODS))],
                'paid_amount' => $money, 'confirmed' => ['sometimes', 'boolean'],
                'reference' => ['nullable', 'string', 'max:120'],
            ];
        }
        if (in_array($action, ['cancel', 'void', 'refund'], true)) {
            $rules['reason'] = ['required', 'string', 'max:250'];
        }
        if ($action === 'cancel') {
            $rules['order_id'] = ['required', 'integer', 'min:1'];
        }

        return $this->result($branch, $billing->execute($counter, $request->validate($rules)));
    }

    private function result(string $branch, int $bill): JsonResponse
    {
        return response()->json(['bill_url' => route('staff.pos.bill', [$branch, $bill])]);
    }
}
