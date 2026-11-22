<?php

namespace Tests\Feature;

use App\Domain\Pos\Billing;
use App\Domain\Pos\Counter;
use App\Domain\Pos\Menu;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CounterConcurrencyTest extends TestCase
{
    public function test_two_cashiers_racing_to_settle_a_bill_create_one_payment(): void
    {
        $directory = sys_get_temp_dir().'/pos-race-'.Str::uuid();
        File::makeDirectory($directory);
        $database = $directory.'/database.sqlite';
        touch($database);
        $processes = [];

        try {
            // A dedicated file-backed SQLite DB lets independent processes see committed data.
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();

            $tenant = Restaurant::create(['name' => 'Concurrency Café', 'email' => 'race@example.test']);
            $branch = $tenant->branches()->create(['name' => 'Counter']);
            $branch->forceFill(['pos_billing_mode' => 'pay_later'])->save();
            $module = DB::table('erp_modules')->where('code', 'pos')->value('id');
            $grant = ['restaurant_id' => $tenant->id, 'erp_module_id' => $module, 'is_enabled' => true];
            DB::table('restaurant_entitlements')->insert($grant);
            DB::table('branch_entitlements')->insert($grant + ['branch_id' => $branch->id]);
            Menu::sample($tenant->id, $branch->id);

            $cashiers = [];
            foreach (['first', 'second'] as $name) {
                $cashier = $tenant->staff()->create(['name' => $name, 'email' => "$name@example.test", 'password' => 'unused'])->refresh();
                $cashier->branches()->attach($branch, ['restaurant_id' => $tenant->id]);
                $cashiers[] = $cashier;
            }

            $bill = app(Billing::class)->execute(Counter::open($cashiers[0], (string) $branch->id), [
                'action' => 'order', 'key' => (string) Str::uuid(), 'mode' => 'pay_later', 'expected_total' => '120.00',
                'lines' => [['product_id' => DB::table('menu_products')->where('price_minor', 12000)->value('id'), 'quantity' => 1, 'unit_price_minor' => 12000]],
            ]);

            foreach ($cashiers as $slot => $cashier) {
                $process = new Process([
                    PHP_BINARY, base_path('tests/Support/settle-counter.php'), $directory, (string) $slot,
                    (string) $cashier->id, (string) $branch->id, (string) $bill, (string) Str::uuid(),
                ], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''], timeout: 15);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 5;
            while ((! is_file($directory.'/0') || ! is_file($directory.'/1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory.'/0');
            $this->assertFileExists($directory.'/1');
            touch($directory.'/start');

            $statuses = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $statuses[] = $process->getOutput();
            }
            $this->assertEqualsCanonicalizing(['200', '409'], $statuses);
            $this->assertDatabaseCount('pos_payments', 1);
            $this->assertDatabaseHas('pos_bills', ['id' => $bill, 'status' => 'settled', 'settled_total_minor' => 12000]);
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::disconnect('sqlite');
            File::deleteDirectory($directory);
        }
    }
}
