<?php

namespace Tests\Feature;

use App\Models\CentralAdmin;
use App\Models\Restaurant;
use App\Models\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'local-test-password-123';

    public function test_staff_login_requires_the_restaurant_email_and_uses_its_membership(): void
    {
        $alpha = $this->restaurant('Alpha');
        $beta = $this->restaurant('Beta');
        $this->staff($alpha, 'password from Alpha');
        $betaStaff = $this->staff($beta);
        $credentials = ['email' => 'CASHIER@example.test', 'password' => self::PASSWORD];

        $this->from('/staff/login')->post('/staff/login', $credentials)
            ->assertSessionHasErrors('restaurant_email');
        $this->post('/staff/login', $credentials + ['restaurant_email' => $alpha->email])
            ->assertSessionHasErrors(['email' => 'The supplied credentials are invalid.']);
        $this->post('/staff/login', $credentials + ['restaurant_email' => 'missing@example.test'])
            ->assertSessionHasErrors(['email' => 'The supplied credentials are invalid.']);
        $this->post('/staff/login', $credentials + ['restaurant_email' => strtoupper($beta->email)])
            ->assertRedirectToRoute('staff.home')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertAuthenticatedAs($betaStaff, 'staff');
        $this->assertGuest('central');
    }

    public function test_staff_cannot_see_or_open_unassigned_or_cross_tenant_branches(): void
    {
        $alpha = $this->restaurant('Alpha');
        $beta = $this->restaurant('Beta');
        $staff = $this->staff($alpha);
        $north = $alpha->branches()->create(['name' => 'North Market']);
        $south = $alpha->branches()->create(['name' => 'South Market']);
        $other = $beta->branches()->create(['name' => 'Other Restaurant Outlet']);
        $staff->branches()->attach($north, ['restaurant_id' => $alpha->id]);

        $this->actingAs($staff, 'staff')->get('/staff')
            ->assertOk()->assertSee('North Market')->assertDontSee('South Market')->assertDontSee('Other Restaurant Outlet');
        $this->get('/staff/branches/'.$north->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/staff/branches/'.$south->id)->assertNotFound();
        $this->get('/staff/branches/'.$other->id)->assertNotFound();
        $this->get('/central')->assertRedirectToRoute('central.login');

        $staff->branches()->detach($north);
        $this->get('/staff/branches/'.$north->id)->assertNotFound();
        $staff->branches()->attach($north, ['restaurant_id' => $alpha->id]);
        $north->is_active = false;
        $north->save();
        $this->get('/staff/branches/'.$north->id)->assertNotFound();
        $this->get('/staff')->assertOk()->assertDontSee('North Market');
    }

    public function test_restaurant_deactivation_revokes_staff_access_and_blocks_sign_in(): void
    {
        $restaurant = $this->restaurant('Alpha');
        $staff = $this->staff($restaurant);
        $this->actingAs($staff, 'staff')->get('/staff')->assertOk();

        $restaurant->is_active = false;
        $restaurant->save();

        $this->get('/staff')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest('staff');
        $this->post('/staff/login', [
            'restaurant_email' => $restaurant->email,
            'email' => $staff->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors(['email' => 'The supplied credentials are invalid.']);
    }

    public function test_staff_deactivation_revokes_an_existing_browser_session(): void
    {
        $restaurant = $this->restaurant('Alpha');
        $staff = $this->staff($restaurant);
        $this->post('/staff/login', [
            'restaurant_email' => $restaurant->email,
            'email' => $staff->email,
            'password' => self::PASSWORD,
        ])->assertRedirectToRoute('staff.home');

        Staff::query()->whereKey($staff->id)->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->get('/staff')->assertForbidden();
        $this->assertGuest('staff');
    }

    public function test_database_rejects_cross_tenant_assignments(): void
    {
        $alpha = $this->restaurant('Alpha');
        $beta = $this->restaurant('Beta');
        $staff = $this->staff($alpha);
        $branch = $beta->branches()->create(['name' => 'Beta Outlet']);

        $this->expectException(QueryException::class);

        DB::table('branch_staff')->insert([
            'restaurant_id' => $alpha->id,
            'staff_id' => $staff->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_central_can_provision_accounts_and_manage_assignments_and_status(): void
    {
        $admin = CentralAdmin::create(['email' => 'admin@example.test', 'password' => Hash::make(self::PASSWORD)]);
        $this->post('/central/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirectToRoute('central.home');
        $this->assertAuthenticatedAs($admin, 'central');
        $this->get('/central')->assertOk()->assertSee('Add restaurant');
        $this->assertGuest('staff');
        $this->get('/staff')->assertRedirectToRoute('staff.login');

        $this->post('/central/restaurants', ['name' => 'City Kitchen', 'email' => 'City@Example.test'])
            ->assertRedirect();
        $restaurant = Restaurant::where('email', 'city@example.test')->firstOrFail();
        $url = '/central/restaurants/'.$restaurant->id;
        foreach (['North', 'South'] as $name) {
            $this->post($url.'/branches', ['name' => $name])->assertRedirect($url);
        }

        [$north, $south] = $restaurant->branches()->orderBy('id')->get()->all();
        $this->post($url.'/staff', [
            'name' => 'Cashier',
            'email' => 'Cashier@example.test',
            'password' => self::PASSWORD,
            'branch_ids' => [$north->id, $south->id],
        ])->assertRedirect($url);

        $staff = $restaurant->staff()->sole();
        $this->assertSame('cashier@example.test', $staff->email);
        $this->assertTrue(Hash::check(self::PASSWORD, $staff->password));
        $this->assertEqualsCanonicalizing([$north->id, $south->id], $staff->branches->modelKeys());
        $this->get($url)->assertOk()->assertSee('Cashier')->assertDontSee($staff->password);
        $this->put($url.'/staff/'.$staff->id.'/branches', ['branch_ids' => [$south->id]])->assertRedirect($url);
        $this->assertSame([$south->id], $staff->fresh()->branches->modelKeys());
        $this->patch($url.'/branches/'.$south->id.'/status', ['is_active' => 0])->assertRedirect($url);
        $this->patch($url.'/staff/'.$staff->id.'/status', ['is_active' => 0])->assertRedirect($url);
        $this->patch($url.'/status', ['is_active' => 0])->assertRedirect($url);
        $this->assertFalse($south->fresh()->is_active);
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertFalse($restaurant->fresh()->is_active);
    }

    public function test_central_rejects_foreign_children_and_assignments(): void
    {
        $alpha = $this->restaurant('Alpha');
        $beta = $this->restaurant('Beta');
        $staff = $this->staff($alpha);
        $foreignStaff = $this->staff($beta);
        $foreignBranch = $beta->branches()->create(['name' => 'Beta Outlet']);
        $url = '/central/restaurants/'.$alpha->id;
        $admin = CentralAdmin::create(['email' => 'admin@example.test', 'password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($admin->refresh(), 'central')->from($url);
        $this->post($url.'/staff', [
            'name' => 'Unauthorized Assignment',
            'email' => 'second@example.test',
            'password' => self::PASSWORD,
            'branch_ids' => [$foreignBranch->id],
        ])->assertSessionHasErrors('branch_ids');
        $this->assertDatabaseMissing('staff', ['email' => 'second@example.test']);
        $this->put($url.'/staff/'.$staff->id.'/branches', ['branch_ids' => [$foreignBranch->id]])
            ->assertSessionHasErrors('branch_ids');
        $this->put($url.'/staff/'.$foreignStaff->id.'/branches', [])->assertNotFound();
        $this->patch($url.'/staff/'.$foreignStaff->id.'/status', ['is_active' => 0])->assertNotFound();
        $this->patch($url.'/branches/'.$foreignBranch->id.'/status', ['is_active' => 0])->assertNotFound();
        $this->assertDatabaseCount('branch_staff', 0);
    }

    public function test_staff_cannot_submit_central_changes_and_can_sign_out(): void
    {
        $restaurant = $this->restaurant('Alpha');
        $staff = $this->staff($restaurant);

        $this->actingAs($staff, 'staff')->post('/central/restaurants', [
            'name' => 'Unauthorized Restaurant', 'email' => 'unauthorized@example.test',
        ])->assertRedirectToRoute('central.login');
        $this->assertDatabaseCount('restaurants', 1);
        $this->post('/logout')->assertRedirectToRoute('staff.login');
        $this->get('/staff')->assertRedirectToRoute('staff.login');
    }

    public function test_login_is_rate_limited_and_malformed_input_renders_safely(): void
    {
        $this->get('/central/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->from('/staff/login')->post('/staff/login', ['email' => ['bad input']])->assertRedirect('/staff/login');
        $this->get('/staff/login')->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/central/login', [
                'email' => 'admin@example.test',
                'password' => 'wrong',
                'restaurant_email' => "ignored-$attempt@example.test",
            ])->assertSessionHasErrors('email');
        }

        $this->post('/central/login', ['email' => 'ADMIN@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    private function restaurant(string $name): Restaurant
    {
        return Restaurant::create(['name' => $name, 'email' => strtolower($name).'@example.test']);
    }

    private function staff(Restaurant $restaurant, string $password = self::PASSWORD): Staff
    {
        return $restaurant->staff()->create([
            'name' => 'Cashier',
            'email' => 'cashier@example.test',
            'password' => Hash::make($password),
        ])->refresh();
    }
}
