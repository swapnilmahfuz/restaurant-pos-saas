<?php

namespace App\Domain\Pos;

use App\Domain\Commercial\ModuleAccess;
use App\Models\Branch;
use App\Models\Staff;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class Counter
{
    public const ROLES = ['owner', 'manager', 'cashier'];

    public const METHODS = ['cash' => 'Cash', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card', 'other' => 'Other'];

    private function __construct(public readonly Staff $staff, public readonly Branch $branch) {}

    public static function open(Staff $staff, string $branch): self
    {
        $outlet = $staff->activeBranches()->findOrFail($branch);
        abort_unless(
            in_array($staff->role, self::ROLES, true)
                && app(ModuleAccess::class)->forBranch($staff, $branch)->contains('code', 'pos'),
            403,
        );

        return new self($staff, $outlet);
    }

    public function manages(): bool
    {
        return in_array($this->staff->role, ['owner', 'manager'], true);
    }

    public function locked(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            $branch = Branch::query()->where('restaurant_id', $this->staff->restaurant_id)->lockForUpdate()->findOrFail($this->branch->id);
            $counter = self::open($this->staff->fresh(), (string) $branch->id);

            return $operation($counter);
        }, 5);
    }

    public function scope(): array
    {
        return ['restaurant_id' => $this->staff->restaurant_id, 'branch_id' => $this->branch->id];
    }

    public function rows(string $table): Builder
    {
        return DB::table($table)->where($this->scope());
    }

    public function methods(): array
    {
        return $this->branch->pos_payment_methods ?? ['cash'];
    }

    public function audit(string $event, array $details): void
    {
        DB::table('pos_audit_events')->insert($this->scope() + [
            'staff_id' => $this->staff->id, 'event' => $event,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
