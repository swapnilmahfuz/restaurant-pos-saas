<?php

namespace App\Domain\Pos;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class Menu
{
    public static function available(Counter $counter): Builder
    {
        return DB::table('menu_products as p')
            ->join('menu_categories as c', fn ($join) => $join->on('c.restaurant_id', '=', 'p.restaurant_id')->on('c.id', '=', 'p.category_id'))
            ->join('branch_products as b', fn ($join) => $join->on('b.restaurant_id', '=', 'p.restaurant_id')->on('b.product_id', '=', 'p.id'))
            ->where('p.restaurant_id', $counter->staff->restaurant_id)
            ->where('b.branch_id', $counter->branch->id)
            ->where('b.available', true)->where('p.active', true)->where('c.active', true)
            ->select('p.id', 'p.name', 'p.description', 'p.category_id', 'c.name as category')
            ->selectRaw('COALESCE(b.price_minor, p.price_minor) as price_minor');
    }

    public static function sample(int $restaurant, int $branch): void
    {
        $category = DB::table('menu_categories')->insertGetId([
            'restaurant_id' => $restaurant, 'name' => 'Sample café', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['Sample coffee' => 12000, 'Sample sandwich' => 19000] as $name => $price) {
            $product = DB::table('menu_products')->insertGetId([
                'restaurant_id' => $restaurant, 'name' => $name, 'category_id' => $category,
                'price_minor' => $price, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('branch_products')->insert([
                'restaurant_id' => $restaurant, 'branch_id' => $branch, 'product_id' => $product, 'available' => true,
            ]);
        }
    }
}
