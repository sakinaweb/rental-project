<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Notifications\FcmPushDriver;
use App\Services\Notifications\LogPushDriver;
use App\Services\Notifications\PushDriver;
use App\Services\Sms\AndroidSmsGateway;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Support\DatabaseTranslationLoader;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Generic SMS goes to the log; OTP uses Twilio Verify instead when TWILIO_* is set (OtpService).
        $this->app->bind(SmsDriver::class, fn () => filled(config('services.sms_gateway.url')) ? new AndroidSmsGateway : new LogSmsDriver);

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
        // Behind HTTPS that ends at a proxy (load balancer, Cloudflare, ngrok) PHP sees plain http; links must still be https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Password reset email in our own wording (site.reset_mail.*); it is sent in the user's locale (User::preferredLocale).
        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject(__('site.reset_mail.subject'))
            ->line(__('site.reset_mail.intro'))
            ->action(__('site.reset_mail.action'), route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line(__('site.reset_mail.expiry', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('site.reset_mail.ignore')));
    }
}
