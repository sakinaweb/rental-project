<?php

namespace App\Providers;

use App\Services\Notifications\FcmPushDriver;
use App\Services\Notifications\LogPushDriver;
use App\Services\Notifications\PushDriver;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Support\DatabaseTranslationLoader;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ponytail: only the log driver exists; add an SMS_DRIVER config switch with the first real provider.
        $this->app->bind(SmsDriver::class, LogSmsDriver::class);

        $this->app->extend('translation.loader', fn (FileLoader $loader) => DatabaseTranslationLoader::from($loader));

        // FCM when a Firebase service-account file is configured (FCM_CREDENTIALS), otherwise the log driver.
        $this->app->bind(PushDriver::class, function () {
            $path = config('services.fcm.credentials');

            return $path && is_file($path)
                ? new FcmPushDriver(json_decode((string) file_get_contents($path), true))
                : new LogPushDriver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
