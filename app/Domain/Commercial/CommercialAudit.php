<?php

namespace App\Domain\Commercial;

use App\Models\Branch;
use App\Models\CentralAdmin;
use App\Models\Restaurant;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final class CommercialAudit
{
    public static function record(
        string $event,
        CentralAdmin|Staff|null $actor,
        ?Restaurant $restaurant = null,
        ?Branch $branch = null,
        array $details = [],
    ): void {
        DB::table('commercial_audit_events')->insert([
            'event' => $event,
            'actor_type' => match (true) {
                $actor instanceof CentralAdmin => 'central',
                $actor instanceof Staff => 'staff',
                default => 'public',
            },
            'actor_id' => $actor?->id,
            'restaurant_id' => $restaurant?->id,
            'branch_id' => $branch?->id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
