<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\PayoutBatchController;
use App\Http\Controllers\Admin\PricingRuleController as AdminPricingRuleController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Api\V1\AddonController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Route;

// Base: /api/v1 (MD §4). Auth via Sanctum bearer token.

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register')->middleware('throttle:10,1');
    Route::post('login', 'login')->middleware('throttle:10,1');
    Route::post('otp/request', 'requestOtp')->middleware('throttle:5,1');
    Route::post('otp/verify', 'verifyOtp')->middleware('throttle:10,1');
});

Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::patch('me', [AuthController::class, 'updateMe'])->middleware('auth:sanctum');
Route::get('addons', [AddonController::class, 'index']);

Route::get('countries', [GeographyController::class, 'countries']);
Route::get('cities', [GeographyController::class, 'cities']);

Route::get('vehicle-categories', [VehicleController::class, 'categories']);
Route::get('vehicles', [VehicleController::class, 'index']);
Route::get('vehicles/{id}', [VehicleController::class, 'show'])->whereNumber('id');
Route::get('vehicles/{id}/availability', [VehicleController::class, 'availability'])->whereNumber('id');

Route::post('bookings/quote', [BookingController::class, 'quote']);

Route::middleware('auth:sanctum')->prefix('bookings')->controller(BookingController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store')->middleware('throttle:20,1');
    Route::get('{booking}', 'show')->whereNumber('booking');
    Route::get('{booking}/invoice', 'invoice')->whereNumber('booking');
    Route::post('{booking}/confirm', 'confirm')->whereNumber('booking');
    Route::post('{booking}/cancel', 'cancel')->whereNumber('booking');
    Route::post('{booking}/extend', 'extend')->whereNumber('booking');
});

// Called by the gateways (no auth); the driver verifies the signature.
Route::post('payments/webhook/{gateway}', [PaymentController::class, 'webhook'])->whereIn('gateway', PaymentService::GATEWAYS);

Route::get('payments/gateways', [PaymentController::class, 'gateways']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('devices', [DeviceController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('devices/{token}', [DeviceController::class, 'destroy'])->where('token', '.+');
    Route::post('payments/intent', [PaymentController::class, 'intent'])->middleware('throttle:20,1');
    Route::get('wallet', [WalletController::class, 'show']);
    Route::post('wallet/topup', [WalletController::class, 'topup'])->middleware('throttle:10,1');
});

// Partner module (MD §4, Phase 9): only where the partner feature is on (global flag + the user's country pack).
Route::middleware(['auth:sanctum', 'partner.module'])->prefix('partner')->controller(PartnerController::class)->group(function () {
    Route::post('apply', 'apply')->middleware('throttle:5,1');
    Route::get('dashboard', 'dashboard');
    Route::get('earnings', 'earnings');
    Route::post('vehicles', 'storeVehicle')->middleware('throttle:20,1');
    Route::post('vehicles/{vehicle}/blockout', 'storeBlockout')->whereNumber('vehicle');
    Route::delete('vehicles/{vehicle}/blockouts/{blockout}', 'destroyBlockout')->whereNumber(['vehicle', 'blockout']);
    Route::post('payout-requests', 'requestPayout')->middleware('throttle:10,1');
});

// Staff app (MD §7.10, Phase 10): the staff member's branch only.
Route::middleware(['auth:sanctum', 'role:staff'])->prefix('staff')->controller(StaffController::class)->group(function () {
    Route::get('bookings', 'index');
    Route::get('bookings/{booking}', 'show')->whereNumber('booking');
    Route::post('bookings/{booking}/pickup', 'pickup')->whereNumber('booking')->middleware('throttle:20,1');
    Route::post('bookings/{booking}/return', 'return')->whereNumber('booking')->middleware('throttle:20,1');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('payout-batches', [PayoutBatchController::class, 'store'])->name('payout-batches.store');
    Route::apiResource('vehicles', AdminVehicleController::class);
    Route::apiResource('pricing-rules', AdminPricingRuleController::class)->only(['index', 'store']);
    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings/{booking}/assign', [AdminBookingController::class, 'assign'])->whereNumber('booking')->name('bookings.assign');
    Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
});
