<?php

use App\Models\CentralAdmin;
use App\Models\Staff;

return [
    'defaults' => ['guard' => 'staff'],
    'guards' => [
        'staff' => ['driver' => 'session', 'provider' => 'staff'],
        'central' => ['driver' => 'session', 'provider' => 'central_admins'],
    ],
    'providers' => [
        'staff' => ['driver' => 'eloquent', 'model' => Staff::class],
        'central_admins' => ['driver' => 'eloquent', 'model' => CentralAdmin::class],
    ],
];
