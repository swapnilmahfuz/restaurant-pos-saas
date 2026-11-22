<?php

use App\Domain\Pos\Billing;
use App\Domain\Pos\Counter;
use App\Models\Staff;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $barrier, $slot, $staff, $branch, $bill, $key] = $argv;
$counter = Counter::open(Staff::findOrFail($staff), $branch);
touch($barrier.'/'.$slot);
$deadline = microtime(true) + 8;
while (! is_file($barrier.'/start')) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}
try {
    $app->make(Billing::class)->execute($counter, [
        'action' => 'settle', 'key' => $key, 'bill_id' => (int) $bill,
        'confirmed' => true, 'method' => 'cash', 'paid_amount' => '120.00',
    ]);
    echo '200';
} catch (HttpException $error) {
    echo $error->getStatusCode();
}
