<?php

namespace Tests\Feature;

use App\Domain\Commercial\ModuleAccess;
use App\Models\CentralAdmin;
use App\Models\Restaurant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommercialFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'commercial-test-password-123';

    public function test_public_registration_and_owner_branch_requests_require_separate_approvals(): void
    {
        $input = $this->application('bistro@example.test');
        $this->get('/register')->assertOk()->assertSee('Request restaurant registration');
        $this->post('/register', $input)->assertRedirectToRoute('registration.received');
        $this->post('/register', $input)->assertRedirectToRoute('registration.received');
        $this->assertDatabaseCount('restaurants', 1);
        $tenant = Restaurant::query()->sole();
        $owner = $tenant->staff()->sole();
        $this->assertTrue(Hash::check(self::PASSWORD, $owner->password));
        $this->assertSame('owner', $owner->role);
        $this->assertSame('pending', $tenant->approval_status);
        $this->login($tenant)->assertSessionHasErrors('email');
        $this->post('/staff/branch-requests', ['name' => 'East'])->assertRedirectToRoute('staff.login');

        $admin = $this->admin();
        $url = '/central/restaurants/'.$tenant->id;
        $this->actingAs($admin, 'central');
        $this->get($url)->assertOk()->assertSee('Approve');
        $this->patch($url.'/status', ['is_active' => true])->assertConflict();
        $this->patch($url.'/licenses/pos', ['enabled' => true])->assertConflict();
        $this->patch($url.'/approval', ['decision' => 'approved'])->assertRedirect($url);
        $this->patch($url.'/approval', ['decision' => 'rejected'])->assertConflict();
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'restaurant.approved', 'actor_id' => $admin->id, 'restaurant_id' => $tenant->id]);
        $this->assertDatabaseCount('restaurant_entitlements', 0);
        $this->login($tenant)->assertRedirectToRoute('staff.home');
        $this->post('/staff/branch-requests', ['name' => 'East', 'is_active' => true])->assertRedirectToRoute('staff.home');
        $branch = $tenant->branches()->sole();
        $this->assertSame('pending', $branch->approval_status);
        $this->assertFalse($branch->is_active);
        $this->get('/staff')->assertSee('East · Pending');
        $this->get('/staff/branches/'.$branch->id)->assertNotFound();
        $this->patch($url.'/branches/'.$branch->id.'/approval', ['decision' => 'approved'])->assertRedirectToRoute('central.login');

        $this->actingAs($admin, 'central')->patch($url.'/branches/'.$branch->id.'/approval', ['decision' => 'approved'])
            ->assertRedirect($url);
        $this->get('/staff/branches/'.$branch->id)->assertOk();
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'branch.approved', 'actor_id' => $admin->id, 'branch_id' => $branch->id]);
    }

    public function test_rejected_registration_cannot_be_activated_or_use_its_credentials(): void
    {
        $this->post('/register', $this->application('rejected@example.test'))->assertRedirect();
        $tenant = Restaurant::query()->sole();
        $this->actingAs($this->admin(), 'central')->patch('/central/restaurants/'.$tenant->id.'/approval', ['decision' => 'rejected'])->assertRedirect();
        $this->patch('/central/restaurants/'.$tenant->id.'/status', ['is_active' => true])->assertConflict();
        $this->login($tenant)->assertSessionHasErrors('email');
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'restaurant.rejected', 'restaurant_id' => $tenant->id]);
    }

    public function test_a_module_requires_both_grants_platform_activation_and_role(): void
    {
        $tenant = Restaurant::create(['name' => 'Bistro', 'email' => 'bistro@example.test']);
        $foreign = Restaurant::create(['name' => 'Elsewhere', 'email' => 'other@example.test']);
        $branch = $tenant->branches()->create(['name' => 'East']);
        $foreignBranch = $foreign->branches()->create(['name' => 'West']);
        $staff = $tenant->staff()->create([
            'name' => 'Cashier', 'email' => 'employee@example.test', 'password' => Hash::make(self::PASSWORD),
        ])->refresh();
        $staff->branches()->attach($branch, ['restaurant_id' => $tenant->id]);
        $url = '/central/restaurants/'.$tenant->id;
        $accessUrl = '/staff/branches/'.$branch->id.'/modules/pos';

        $this->actingAs($staff, 'staff')->get($accessUrl)->assertForbidden();
        $this->get('/staff/branches/'.$branch->id)->assertDontSee('POS and billing');
        $this->patch($url.'/licenses/pos', ['enabled' => true])->assertRedirectToRoute('central.login');
        $this->post('/staff/branch-requests', ['name' => 'No owner'])->assertForbidden();

        $this->actingAs($this->admin(), 'central');
        $this->get('/central/modules')->assertOk()->assertSee('Module registry');
        $this->patch($url.'/branches/'.$foreignBranch->id.'/licenses/pos', ['enabled' => true])->assertNotFound();
        $this->patch($url.'/branches/'.$branch->id.'/licenses/pos', ['enabled' => true])->assertRedirect();
        $this->get($accessUrl)->assertForbidden();
        $this->patch($url.'/licenses/pos', ['enabled' => true])->assertRedirect();
        $this->get($accessUrl)->assertOk()->assertSee('POS and billing')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/staff/branches/'.$branch->id)->assertSee('POS and billing');
        $this->get('/staff/branches/'.$foreignBranch->id.'/modules/pos')->assertNotFound();

        $this->patch('/central/modules/pos', ['enabled' => false])->assertRedirect();
        $this->get($accessUrl)->assertForbidden();
        $this->patch('/central/modules/pos', ['enabled' => true])->assertRedirect();
        $this->patch($url.'/staff/'.$staff->id.'/role', ['role' => 'chef'])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->actingAs($staff->refresh(), 'staff')->get($accessUrl)->assertForbidden();
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'license.platform', 'actor_type' => 'central']);
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'license.branch', 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'staff.role', 'restaurant_id' => $tenant->id]);
    }

    public function test_database_rejects_a_branch_grant_with_a_foreign_restaurant_id(): void
    {
        $a = Restaurant::create(['name' => 'A', 'email' => 'a@example.test']);
        $b = Restaurant::create(['name' => 'B', 'email' => 'b@example.test']);
        $branch = $b->branches()->create(['name' => 'West']);

        $this->expectException(QueryException::class);
        DB::table('branch_entitlements')->insert([
            'restaurant_id' => $a->id, 'branch_id' => $branch->id,
            'erp_module_id' => DB::table('erp_modules')->where('code', 'pos')->value('id'),
            'is_enabled' => true,
        ]);
    }

    public function test_public_demo_sessions_are_isolated_and_reset_retires_only_the_current_tenant(): void
    {
        $this->post('/demo')->assertRedirectToRoute('staff.home');
        $first = $this->app['auth']->guard('staff')->user();
        $firstBranch = $first->branches()->sole();
        $this->get('/staff/branches/'.$firstBranch->id.'/modules/pos')->assertOk();
        $this->post('/logout')->assertRedirect();

        $this->post('/demo')->assertRedirectToRoute('staff.home');
        $second = $this->app['auth']->guard('staff')->user();
        $this->assertNotSame($first->restaurant_id, $second->restaurant_id);
        $this->get('/staff/branches/'.$firstBranch->id.'/modules/pos')->assertNotFound();
        $this->get('/staff')->assertSee('Reset sample demo');
        $this->post('/demo')->assertRedirectToRoute('staff.home');
        $this->assertNotNull($second->restaurant()->firstOrFail()->retired_at);
        $this->assertNull($first->restaurant()->firstOrFail()->retired_at);
        $this->assertDatabaseCount('restaurants', 3);
        $this->expectException(HttpException::class);
        app(ModuleAccess::class)->authorizeExternalActivity($first);
    }

    public function test_trial_expiry_reset_and_conversion_preserve_sandbox_separation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'central')->get('/central/trials/new')->assertOk()->assertSee('Create a private trial');
        $this->post('/central/trials', $this->application('trial@example.test') + [
            'access_days' => 1, 'modules' => ['pos'],
        ])->assertRedirect();
        $trial = Restaurant::query()->sole();
        $branch = $trial->branches()->sole();
        $this->assertSame('trial', $trial->tenant_type);
        $this->get('/central/restaurants/'.$trial->id)->assertOk()->assertSee('Convert to production');
        $this->login($trial)->assertRedirectToRoute('staff.home');
        $this->get('/staff/branches/'.$branch->id.'/modules/pos')->assertOk();
        $this->get('/staff/branches/'.$branch->id.'/modules/kitchen')->assertForbidden();

        $this->travel(2)->days();
        $this->get('/staff')->assertForbidden();
        $this->login($trial)->assertSessionHasErrors('email');
        $this->actingAs($admin, 'central')->post('/central/restaurants/'.$trial->id.'/replacement', $this->application('reset@example.test') + [
            'action' => 'reset', 'access_days' => 7, 'modules' => ['kitchen'],
        ])->assertRedirect();
        $reset = Restaurant::where('email', 'reset@example.test')->sole();
        $this->assertNotNull($trial->fresh()->retired_at);
        $this->assertSame('trial', $reset->tenant_type);
        $this->post('/central/restaurants/'.$reset->id.'/replacement', $this->application('live@example.test') + [
            'action' => 'convert',
        ])->assertRedirect();
        $live = Restaurant::where('email', 'live@example.test')->sole();
        $this->assertSame('production', $live->tenant_type);
        $this->assertDatabaseMissing('restaurant_entitlements', ['restaurant_id' => $live->id]);
        $this->assertCount(0, $live->branches);
        $this->assertNotNull($reset->fresh()->retired_at);
        $this->assertDatabaseHas('commercial_audit_events', ['event' => 'trial.converted', 'restaurant_id' => $reset->id]);
        $this->patch('/central/restaurants/'.$reset->id.'/status', ['is_active' => true])->assertConflict();
        $this->login($live)->assertRedirectToRoute('staff.home');
    }

    private function application(string $email): array
    {
        return [
            'name' => 'Bistro', 'email' => $email, 'owner_name' => 'Owner', 'owner_email' => 'owner@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ];
    }

    private function admin(): CentralAdmin
    {
        return CentralAdmin::create(['email' => 'super-admin@example.test', 'password' => Hash::make(self::PASSWORD)])->refresh();
    }

    private function login(Restaurant $restaurant)
    {
        return $this->post('/staff/login', [
            'restaurant_email' => $restaurant->email, 'email' => 'owner@example.test', 'password' => self::PASSWORD,
        ]);
    }
}
