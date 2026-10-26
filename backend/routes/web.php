<?php

use App\Http\Controllers\Api\V1\BookingController;
use App\Livewire\Account\Bookings;
use App\Livewire\Account\BookingShow;
use App\Livewire\Account\Profile;
use App\Livewire\Account\Wallet;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Checkout;
use App\Livewire\Confirmation;
use App\Livewire\Investor\Dashboard as InvestorDashboard;
use App\Livewire\Partner\Dashboard as PartnerDashboard;
use App\Livewire\Partner\Vehicles as PartnerVehicles;
use App\Livewire\Search;
use App\Livewire\VehicleShow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

// Customer website (MD §7.6): search, booking flow, account area. Pages call the same services as the API.

Route::get('/', Search::class)->name('home');
Route::get('/vehicles/{id}', VehicleShow::class)->whereNumber('id')->name('vehicles.show');

Route::post('/language', function (Request $request) {
    $request->session()->put('locale', $request->validate(['locale' => ['required', Rule::in(config('app.supported_locales'))]])['locale']);

    return back();
})->name('language');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');

    Route::get('/checkout/{booking}', Checkout::class)->whereNumber('booking')->name('checkout');
    Route::get('/bookings/{booking}/confirmation', Confirmation::class)->whereNumber('booking')->name('confirmation');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/bookings', Bookings::class)->name('bookings');
        Route::get('/bookings/{booking}', BookingShow::class)->whereNumber('booking')->name('bookings.show');
        Route::get('/bookings/{booking}/invoice', [BookingController::class, 'invoice'])->whereNumber('booking')->name('bookings.invoice');
        Route::get('/wallet', Wallet::class)->name('wallet');
        Route::get('/profile', Profile::class)->name('profile');
    });

    // Partner area (Phase 9): only where the partner feature is on (global flag + the user's country pack).
    Route::middleware('partner.module')->prefix('partner')->name('partner.')->group(function () {
        Route::get('/', PartnerDashboard::class)->name('dashboard');
        Route::get('/vehicles', PartnerVehicles::class)->name('vehicles');
    });

    // Investor area (Phase 11): only where the investor feature is on (global flag + the user's country pack).
    Route::get('/investor', InvestorDashboard::class)->middleware('investor.module')->name('investor.dashboard');
});
