<?php

use App\Domain\Commercial\ModuleCatalog;
use App\Http\Controllers\CentralWorkspaceController;
use App\Http\Controllers\LicensingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StaffWorkspaceController;
use Illuminate\Support\Facades\Route;

foreach (['restaurant', 'branch', 'staff'] as $parameter) {
    Route::pattern($parameter, '[1-9][0-9]{0,17}');
}

Route::redirect('/', '/staff');
Route::view('register', 'auth.register')->name('registration.form');
Route::view('register/received', 'auth.registration-received')->name('registration.received');
Route::post('register', [OnboardingController::class, 'register'])->middleware('throttle:registration');
Route::post('demo', [SandboxController::class, 'demo'])->middleware('throttle:public-demo')->name('demo.start');

Route::post('logout', [SessionController::class, 'logout'])->name('logout');

Route::prefix('staff')->name('staff.')->group(function (): void {
    Route::view('login', 'auth.login', ['central' => false])->name('login');
    Route::post('login', [SessionController::class, 'staff'])->middleware('throttle:staff-login');

    Route::middleware(['auth:staff', 'active:staff'])->group(function (): void {
        Route::get('/', [StaffWorkspaceController::class, 'index'])->name('home');
        Route::post('branch-requests', [OnboardingController::class, 'requestBranch'])->name('branches.request');
        Route::get('branches/{branch}/modules/{module}', [StaffWorkspaceController::class, 'module'])->name('branches.module');
        Route::get('branches/{branch}', [StaffWorkspaceController::class, 'show'])->name('branches.show');
    });
});

Route::prefix('central')->name('central.')->group(function (): void {
    Route::view('login', 'auth.login', ['central' => true])->name('login');
    Route::post('login', [SessionController::class, 'central'])->middleware('throttle:central-login');

    Route::middleware(['auth:central', 'active:central'])->scopeBindings()->group(function (): void {
        Route::get('/', [CentralWorkspaceController::class, 'index'])->name('home');
        Route::get('modules', [LicensingController::class, 'index'])->name('modules.index');
        Route::patch('modules/{module}', [LicensingController::class, 'platform'])->name('modules.update');
        Route::get('trials/new', fn () => view('central.trial', ['modules' => ModuleCatalog::MODULES]))->name('trials.new');
        Route::post('trials', [SandboxController::class, 'trial'])->name('trials.store');
        Route::post('restaurants', [CentralWorkspaceController::class, 'createRestaurant'])->name('restaurants.store');

        Route::prefix('restaurants/{restaurant}')->name('restaurants.')->group(function (): void {
            Route::get('/', [CentralWorkspaceController::class, 'show'])->name('show');
            Route::patch('approval', [OnboardingController::class, 'decideRestaurant'])->name('approval');
            Route::patch('branches/{branch}/approval', [OnboardingController::class, 'decideBranch'])->name('branches.approval');
            Route::patch('expiration', [SandboxController::class, 'expiration'])->name('expiration');
            Route::post('replacement', [SandboxController::class, 'replaceTrial'])->name('replacement');
            Route::patch('licenses/{module}', [LicensingController::class, 'restaurant'])->name('licenses');
            Route::patch('branches/{branch}/licenses/{module}', [LicensingController::class, 'branch'])->name('branches.licenses');
            Route::patch('staff/{staff}/role', [CentralWorkspaceController::class, 'updateRole'])->name('staff.role');
            Route::patch('status', [CentralWorkspaceController::class, 'updateRestaurantStatus'])->name('status');
            Route::post('branches', [CentralWorkspaceController::class, 'createBranch'])->name('branches.store');
            Route::patch('branches/{branch}/status', [CentralWorkspaceController::class, 'updateBranchStatus'])->name('branches.status');
            Route::post('staff', [CentralWorkspaceController::class, 'createStaff'])->name('staff.store');
            Route::patch('staff/{staff}/status', [CentralWorkspaceController::class, 'updateStaffStatus'])->name('staff.status');
            Route::put('staff/{staff}/branches', [CentralWorkspaceController::class, 'updateAssignments'])->name('staff.branches');
        });
    });
});
