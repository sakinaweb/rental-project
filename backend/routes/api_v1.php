<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\PricingRuleController as AdminPricingRuleController;
use App\Http\Controllers\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

// Base: /api/v1 (MD §4). Auth via Sanctum bearer token.

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register')->middleware('throttle:10,1');
    Route::post('login', 'login')->middleware('throttle:10,1');
    Route::post('otp/request', 'requestOtp')->middleware('throttle:5,1');
    Route::post('otp/verify', 'verifyOtp')->middleware('throttle:10,1');
});

Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');

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
    Route::post('{booking}/confirm', 'confirm')->whereNumber('booking');
    Route::post('{booking}/cancel', 'cancel')->whereNumber('booking');
    Route::post('{booking}/extend', 'extend')->whereNumber('booking');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('vehicles', AdminVehicleController::class);
    Route::apiResource('pricing-rules', AdminPricingRuleController::class)->only(['index', 'store']);
    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
});
