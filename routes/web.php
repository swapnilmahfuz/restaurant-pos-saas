<?php

use App\Http\Controllers\CentralWorkspaceController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StaffWorkspaceController;
use Illuminate\Support\Facades\Route;

foreach (['restaurant', 'branch', 'staff'] as $parameter) {
    Route::pattern($parameter, '[1-9][0-9]{0,17}');
}

Route::redirect('/', '/staff');
Route::post('logout', [SessionController::class, 'logout'])->name('logout');

Route::prefix('staff')->name('staff.')->group(function (): void {
    Route::view('login', 'auth.login', ['central' => false])->name('login');
    Route::post('login', [SessionController::class, 'staff'])->middleware('throttle:staff-login');

    Route::middleware(['auth:staff', 'active:staff'])->group(function (): void {
        Route::get('/', [StaffWorkspaceController::class, 'index'])->name('home');
        Route::get('branches/{branch}', [StaffWorkspaceController::class, 'show'])->name('branches.show');
    });
});

Route::prefix('central')->name('central.')->group(function (): void {
    Route::view('login', 'auth.login', ['central' => true])->name('login');
    Route::post('login', [SessionController::class, 'central'])->middleware('throttle:central-login');

    Route::middleware(['auth:central', 'active:central'])->scopeBindings()->group(function (): void {
        Route::get('/', [CentralWorkspaceController::class, 'index'])->name('home');
        Route::post('restaurants', [CentralWorkspaceController::class, 'createRestaurant'])->name('restaurants.store');

        Route::prefix('restaurants/{restaurant}')->name('restaurants.')->group(function (): void {
            Route::get('/', [CentralWorkspaceController::class, 'show'])->name('show');
            Route::patch('status', [CentralWorkspaceController::class, 'updateRestaurantStatus'])->name('status');
            Route::post('branches', [CentralWorkspaceController::class, 'createBranch'])->name('branches.store');
            Route::patch('branches/{branch}/status', [CentralWorkspaceController::class, 'updateBranchStatus'])->name('branches.status');
            Route::post('staff', [CentralWorkspaceController::class, 'createStaff'])->name('staff.store');
            Route::patch('staff/{staff}/status', [CentralWorkspaceController::class, 'updateStaffStatus'])->name('staff.status');
            Route::put('staff/{staff}/branches', [CentralWorkspaceController::class, 'updateAssignments'])->name('staff.branches');
        });
    });
});
