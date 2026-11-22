<?php

namespace App\Http\Controllers;

use App\Domain\Pos\Counter;
use App\Domain\Pos\Money;
use App\Models\MenuProduct;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosManagementController
{
    public function menu(Request $request, string $branch): View
    {
        $counter = $this->manager($request, $branch);
        $restaurant = $counter->staff->restaurant_id;
        $products = MenuProduct::query()->where('restaurant_id', $restaurant)->orderBy('name')->orderBy('id')->simplePaginate(25);

        return view('pos.menu', [
            'counter' => $counter, 'products' => $products,
            'categories' => DB::table('menu_categories')->where('restaurant_id', $restaurant)->orderBy('name')->get()->keyBy('id'),
            'options' => $counter->rows('branch_products')->whereIn('product_id', $products->getCollection()->modelKeys())->get()->keyBy('product_id'),
        ]);
    }

    public function category(Request $request, string $branch): RedirectResponse
    {
        $counter = $this->manager($request, $branch);
        $input = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'active' => ['required', 'boolean'],
        ]);

        try {
            $counter->locked(function (Counter $counter) use ($input) {
                abort_unless($counter->manages(), 403);
                $restaurant = $counter->staff->restaurant_id;
                $values = ['name' => $input['name'], 'active' => (bool) $input['active'], 'updated_at' => now()];
                if (isset($input['id'])) {
                    abort_unless($counter->staff->role === 'owner', 403);
                    $category = DB::table('menu_categories')->where('restaurant_id', $restaurant)->where('id', $input['id']);
                    $category->firstOrFail();
                    $category->update($values);
                } else {
                    DB::table('menu_categories')->insert($values + ['restaurant_id' => $restaurant, 'created_at' => now()]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'A category with this name already exists.']);
        }

        return to_route('staff.pos.manage', $branch);
    }

    public function product(Request $request, string $branch): RedirectResponse
    {
        $counter = $this->manager($request, $branch);
        $input = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'string', Money::INPUT],
            'active' => ['required', 'boolean'],
        ]);

        $counter->locked(function (Counter $counter) use ($input) {
            abort_unless($counter->manages(), 403);
            $restaurant = $counter->staff->restaurant_id;
            $category = DB::table('menu_categories')->where('restaurant_id', $restaurant)->where('id', $input['category_id'])->firstOrFail();
            $values = [
                'category_id' => $category->id, 'name' => $input['name'], 'description' => $input['description'] ?? null,
                'price_minor' => Money::minor($input['price']), 'active' => (bool) $input['active'],
            ];
            if (isset($input['id'])) {
                abort_unless($counter->staff->role === 'owner', 403);
                MenuProduct::query()->where('restaurant_id', $restaurant)->findOrFail($input['id'])->update($values);
            } else {
                $product = MenuProduct::create($values + ['restaurant_id' => $restaurant]);
                DB::table('branch_products')->insert($counter->scope() + ['product_id' => $product->id, 'available' => true]);
            }
        });

        return to_route('staff.pos.manage', $branch);
    }

    public function availability(Request $request, string $branch): RedirectResponse
    {
        $counter = $this->manager($request, $branch);
        $input = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'price' => ['nullable', 'string', Money::INPUT],
            'available' => ['required', 'boolean'],
        ]);

        $counter->locked(function (Counter $counter) use ($input) {
            abort_unless($counter->manages(), 403);
            $product = MenuProduct::query()->where('restaurant_id', $counter->staff->restaurant_id)->findOrFail($input['product_id']);
            DB::table('branch_products')->updateOrInsert($counter->scope() + ['product_id' => $product->id], [
                'available' => (bool) $input['available'],
                'price_minor' => isset($input['price']) ? Money::minor($input['price']) : null,
            ]);
        });

        return to_route('staff.pos.manage', $branch);
    }

    public function settings(Request $request, string $branch): RedirectResponse
    {
        $counter = $this->manager($request, $branch);
        abort_unless($counter->staff->role === 'owner', 403);
        $input = $request->validate([
            'mode' => ['required', Rule::in(['pay_first', 'pay_later'])],
            'methods' => ['required', 'array', 'list', 'min:1'],
            'methods.*' => ['distinct', Rule::in(array_keys(Counter::METHODS))],
        ]);

        $counter->locked(function (Counter $counter) use ($input) {
            abort_unless($counter->staff->role === 'owner', 403);
            $outlet = $counter->branch;
            if ($outlet->pos_billing_mode !== $input['mode'] && $counter->rows('pos_bills')->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['mode' => 'Settle or void all open visits before changing billing mode.']);
            }
            $outlet->pos_billing_mode = $input['mode'];
            $outlet->pos_payment_methods = $input['methods'];
            if ($outlet->isDirty()) {
                $counter->audit('settings.changed', [
                    'previous_mode' => $outlet->getOriginal('pos_billing_mode'),
                    'mode' => $input['mode'], 'methods' => $input['methods'],
                ]);
                $outlet->save();
            }
        });

        return to_route('staff.pos.manage', $branch);
    }

    private function manager(Request $request, string $branch): Counter
    {
        $counter = Counter::open($request->user('staff'), $branch);
        abort_unless($counter->manages(), 403);

        return $counter;
    }
}
