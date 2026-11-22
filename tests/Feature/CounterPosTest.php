<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CounterPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_management_and_pricing_are_scoped_and_bill_snapshots_survive_edits(): void
    {
        [$tenant, $branch, $owner] = $this->fixture();
        $otherBranch = $tenant->branches()->create(['name' => 'Second']);
        $this->grantBranch($tenant, $otherBranch);
        [$foreignTenant, $foreignBranch, $foreignOwner] = $this->fixture('Foreign');
        $this->actingAs($foreignOwner, 'staff');
        $foreignProduct = $this->product($foreignBranch);
        $this->actingAs($owner, 'staff');
        $root = $this->root($branch);
        $product = $this->product($branch);

        $this->get($root.'/manage')->assertOk()->assertSee('Flat white');
        $this->get($this->root($otherBranch).'/menu')->assertNotFound();
        $this->post($root.'/manage/availability', ['product_id' => $foreignProduct, 'available' => true])->assertNotFound();
        $owner->branches()->attach($otherBranch, ['restaurant_id' => $tenant->id]);
        $this->getJson($this->root($otherBranch).'/menu')->assertOk()->assertJsonCount(0, 'products');
        $this->post($this->root($otherBranch).'/manage/availability', [
            'product_id' => $product, 'available' => true, 'price' => '9.50',
        ])->assertRedirect();
        $this->getJson($this->root($otherBranch).'/menu?q=flat')->assertOk()->assertJsonPath('products.0.price_minor', 950);
        $this->getJson($root.'/menu')->assertOk()->assertJsonPath('products.0.price_minor', 1250);

        $order = $this->order($product);
        $receipt = $this->postJson($root.'/requests', $order)->assertOk()->json('bill_url');
        $category = DB::table('menu_categories')->where('restaurant_id', $tenant->id)->value('id');
        $this->post($root.'/manage/products', [
            'id' => $product, 'name' => 'Renamed', 'category_id' => $category, 'price' => '20.00', 'active' => false,
        ])->assertRedirect();
        $this->get($receipt)->assertOk()->assertSee('Flat white')->assertSee('12.50')->assertDontSee('Renamed');
        $this->getJson($root.'/menu')->assertOk()->assertJsonCount(0, 'products');
        $this->postJson($root.'/requests', array_replace($order, ['key' => $this->key()]))->assertConflict();
        $this->actingAs($foreignOwner, 'staff')->get($receipt)->assertNotFound();
    }

    public function test_pay_first_requires_explicit_payment_and_reused_tokens_create_distinct_orders(): void
    {
        [, $branch, $owner] = $this->fixture();
        $this->actingAs($owner, 'staff');
        $product = $this->product($branch);
        $root = $this->root($branch);
        $this->get($root)->assertOk()->assertSee('Confirm payment and order')->assertHeader('Cache-Control', 'no-store, private');
        $order = $this->order($product) + ['collection_token' => 'T-7'];
        $this->postJson($root.'/requests', array_replace($order, ['confirmed' => false]))->assertUnprocessable();
        $this->postJson($root.'/requests', array_replace($order, ['method' => 'card']))->assertUnprocessable();
        $this->postJson($root.'/requests', array_replace($order, ['paid_amount' => '12.49']))->assertConflict();
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_payments', 0);

        $response = $this->postJson($root.'/requests', $order)->assertOk()->json();
        $this->postJson($root.'/requests', $order)->assertOk()->assertExactJson($response);
        $this->getJson($root.'/requests/'.$order['key'])->assertOk()->assertExactJson($response);
        $this->postJson($root.'/requests', array_replace($order, ['collection_token' => 'T-8']))->assertConflict();
        $this->postJson($root.'/requests', array_replace($order, ['key' => $this->key()]))->assertOk();
        $this->assertDatabaseCount('pos_bills', 2);
        $this->assertDatabaseCount('pos_payments', 2);
        $this->assertSame(2, DB::table('pos_orders')->where('collection_token', 'T-7')->where('preparation_status', 'queued')->distinct()->count('number'));
        $this->assertDatabaseHas('pos_bills', ['status' => 'settled', 'simulated' => false, 'settled_total_minor' => 1250]);
    }

    public function test_pay_later_accumulates_orders_then_settles_once_and_blocks_mode_changes_while_open(): void
    {
        [, $branch, $owner] = $this->fixture();
        $this->actingAs($owner, 'staff');
        $product = $this->product($branch);
        $root = $this->root($branch);
        $this->post($root.'/manage/settings', ['mode' => 'pay_later', 'methods' => ['cash', 'card']])->assertRedirect();
        $order = $this->unpaidOrder($product) + ['visit_label' => 'Dine-in', 'discount' => '0.25', 'vat' => '0.10', 'sd' => '0.20', 'service' => '0.05'];
        $order['expected_total'] = '12.60';
        $url = $this->postJson($root.'/requests', $order)->assertOk()->json('bill_url');
        $bill = DB::table('pos_bills')->sole();
        $this->assertNull($bill->settled_at);
        $this->assertDatabaseCount('pos_payments', 0);
        $this->assertDatabaseHas('pos_orders', [
            'bill_id' => $bill->id, 'subtotal_minor' => 1250, 'discount_minor' => 25, 'vat_minor' => 10,
            'sd_minor' => 20, 'service_minor' => 5, 'total_minor' => 1260, 'preparation_status' => 'queued',
        ]);
        $this->get($url)->assertOk()->assertSee('Unpaid bill')->assertSee('12.60');
        $this->postJson($root.'/manage/settings', ['mode' => 'pay_first', 'methods' => ['cash']])->assertUnprocessable();

        $this->postJson($root.'/requests', $this->unpaidOrder($product) + ['bill_id' => $bill->id])->assertOk();
        $this->assertDatabaseCount('pos_bills', 1);
        $this->assertDatabaseCount('pos_orders', 2);
        $this->get($url)->assertOk()->assertSee('25.10');

        $settle = $this->action('settle', $bill->id) + ['confirmed' => true, 'method' => 'card', 'paid_amount' => '25.10'];
        $this->postJson($root.'/requests', $settle)->assertUnprocessable();
        $settle['reference'] = 'card-terminal-4';
        $this->postJson($root.'/requests', array_replace($settle, ['paid_amount' => '12.60']))->assertConflict();
        $this->postJson($root.'/requests', $settle)->assertOk();
        $this->postJson($root.'/requests', $settle)->assertOk();
        $this->postJson($root.'/requests', array_replace($settle, ['key' => $this->key()]))->assertConflict();
        $this->assertDatabaseCount('pos_payments', 1);
        $this->assertDatabaseHas('pos_payments', ['amount_minor' => 2510, 'reference' => 'card-terminal-4', 'kind' => 'payment']);
        $this->assertDatabaseHas('pos_bills', ['status' => 'settled', 'settled_total_minor' => 2510]);
        $this->get($url)->assertOk()->assertSee('Paid bill');
        $this->post($root.'/manage/settings', ['mode' => 'pay_first', 'methods' => ['cash']])->assertRedirect();
    }

    public function test_sensitive_actions_require_roles_and_financial_history_is_retained(): void
    {
        [$tenant, $branch, $owner] = $this->fixture();
        $cashier = $this->staff($tenant, $branch, 'cashier');
        $manager = $this->staff($tenant, $branch, 'manager');
        $this->actingAs($owner, 'staff');
        $root = $this->root($branch);
        $product = $this->product($branch);
        $this->post($root.'/manage/settings', ['mode' => 'pay_later', 'methods' => ['cash']])->assertRedirect();
        $this->actingAs($cashier, 'staff');
        $this->get($root.'/manage')->assertForbidden();
        $this->postJson($root.'/requests', $this->unpaidOrder($product) + ['discount' => '1.00'])->assertForbidden();
        $this->postJson($root.'/requests', $this->unpaidOrder($product))->assertOk();
        $bill = DB::table('pos_bills')->sole();
        $order = DB::table('pos_orders')->sole();
        $cancel = $this->action('cancel', $bill->id) + ['order_id' => $order->id, 'reason' => 'Wrong item'];
        $void = $this->action('void', $bill->id) + ['reason' => 'Visit abandoned'];
        $this->postJson($root.'/requests', $cancel)->assertForbidden();
        $this->postJson($root.'/requests', $void)->assertForbidden();
        $this->actingAs($manager, 'staff');
        $this->postJson($root.'/manage/settings', ['mode' => 'pay_first', 'methods' => ['cash']])->assertForbidden();
        $this->postJson($root.'/requests', $cancel)->assertOk();
        $this->assertDatabaseHas('pos_orders', ['id' => $order->id, 'status' => 'cancelled', 'preparation_status' => 'cancelled']);
        $this->postJson($root.'/requests', $void)->assertOk();
        $this->assertDatabaseHas('pos_bills', ['id' => $bill->id, 'status' => 'voided', 'settled_at' => null]);

        $this->actingAs($owner, 'staff')->post($root.'/manage/settings', ['mode' => 'pay_first', 'methods' => ['cash']])->assertRedirect();
        $this->actingAs($cashier, 'staff')->postJson($root.'/requests', $this->order($product))->assertOk();
        $paid = DB::table('pos_bills')->where('status', 'settled')->sole();
        $refund = $this->action('refund', $paid->id) + ['reason' => 'Returned', 'confirmed' => true];
        $this->postJson($root.'/requests', $refund)->assertForbidden();
        $this->actingAs($manager, 'staff')->postJson($root.'/requests', $refund)->assertOk();
        $this->postJson($root.'/requests', array_replace($refund, ['key' => $this->key()]))->assertConflict();
        $this->assertDatabaseHas('pos_bills', ['id' => $paid->id, 'status' => 'refunded', 'settled_total_minor' => 1250]);
        $this->assertDatabaseHas('pos_payments', ['bill_id' => $paid->id, 'kind' => 'payment', 'amount_minor' => 1250]);
        $this->assertDatabaseHas('pos_payments', ['bill_id' => $paid->id, 'kind' => 'refund', 'amount_minor' => 1250]);
        $this->assertDatabaseHas('pos_audit_events', ['event' => 'bill.refund', 'staff_id' => $manager->id]);
        $this->assertDatabaseHas('pos_audit_events', ['event' => 'bill.void', 'staff_id' => $manager->id]);
        $this->assertDatabaseCount('pos_order_lines', 2);
    }

    public function test_pos_roles_grants_and_sandbox_bills_remain_isolated(): void
    {
        [$tenant, $branch, $owner] = $this->fixture();
        $cashier = $this->staff($tenant, $branch, 'cashier');
        $waiter = $this->staff($tenant, $branch, 'waiter');
        $root = $this->root($branch);
        $this->actingAs($waiter, 'staff')->get($root)->assertForbidden();
        $this->actingAs($owner, 'staff')->get($root)->assertOk();
        DB::table('branch_entitlements')->where('branch_id', $branch->id)->update(['is_enabled' => false]);
        $this->actingAs($cashier, 'staff')->get($root)->assertForbidden();
        DB::table('branch_entitlements')->where('branch_id', $branch->id)->update(['is_enabled' => true]);
        DB::table('restaurant_entitlements')->where('restaurant_id', $tenant->id)->update(['is_enabled' => false]);
        $this->get($root)->assertForbidden();

        foreach (['trial', 'demo'] as $type) {
            [$sample, $outlet, $staff] = $this->fixture($type);
            $sample->forceFill(['tenant_type' => $type, 'access_expires_at' => now()->addHour()])->save();
            $this->actingAs($staff, 'staff');
            $product = $this->product($outlet);
            $url = $this->postJson($this->root($outlet).'/requests', $this->order($product))->assertOk()->json('bill_url');
            $this->get($url)->assertOk()->assertSee('Payments and refunds are simulated.');
            $this->assertDatabaseHas('pos_bills', ['restaurant_id' => $sample->id, 'simulated' => true]);
            $this->actingAs($owner, 'staff')->get($url)->assertNotFound();
        }
    }

    private function fixture(string $name = 'Bistro'): array
    {
        $tenant = Restaurant::create(['name' => $name, 'email' => strtolower($name).'@example.test']);
        $branch = $tenant->branches()->create(['name' => 'Main']);
        $module = DB::table('erp_modules')->where('code', 'pos')->value('id');
        DB::table('restaurant_entitlements')->insert(['restaurant_id' => $tenant->id, 'erp_module_id' => $module, 'is_enabled' => true]);
        $this->grantBranch($tenant, $branch);

        return [$tenant, $branch, $this->staff($tenant, $branch, 'owner')];
    }

    private function grantBranch(Restaurant $tenant, Branch $branch): void
    {
        DB::table('branch_entitlements')->insert([
            'restaurant_id' => $tenant->id, 'branch_id' => $branch->id,
            'erp_module_id' => DB::table('erp_modules')->where('code', 'pos')->value('id'), 'is_enabled' => true,
        ]);
    }

    private function staff(Restaurant $tenant, Branch $branch, string $role): Staff
    {
        $staff = $tenant->staff()->make(['name' => ucfirst($role), 'email' => $role.'@example.test', 'password' => 'unused']);
        $staff->forceFill(['role' => $role])->save();
        $staff->branches()->attach($branch, ['restaurant_id' => $tenant->id]);

        return $staff->refresh();
    }

    private function product(Branch $branch): int
    {
        $root = $this->root($branch);
        $this->post($root.'/manage/categories', ['name' => 'Coffee', 'active' => true])->assertRedirect();
        $category = DB::table('menu_categories')->where('restaurant_id', $branch->restaurant_id)->sole();
        $this->post($root.'/manage/products', [
            'category_id' => $category->id, 'name' => 'Flat white', 'description' => 'House espresso',
            'price' => '12.50', 'active' => true,
        ])->assertRedirect();

        return DB::table('menu_products')->where('restaurant_id', $branch->restaurant_id)->value('id');
    }

    private function root(Branch $branch): string
    {
        return '/staff/branches/'.$branch->id.'/pos';
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function action(string $action, int $bill): array
    {
        return ['action' => $action, 'key' => $this->key(), 'bill_id' => $bill];
    }

    private function unpaidOrder(int $product): array
    {
        return [
            'action' => 'order', 'key' => $this->key(), 'mode' => 'pay_later', 'expected_total' => '12.50',
            'lines' => [['product_id' => $product, 'quantity' => 1, 'unit_price_minor' => 1250, 'note' => 'No sugar']],
        ];
    }

    private function order(int $product): array
    {
        return array_replace($this->unpaidOrder($product), [
            'mode' => 'pay_first', 'confirmed' => true, 'method' => 'cash', 'paid_amount' => '12.50',
        ]);
    }
}
