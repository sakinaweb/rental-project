<?php

namespace App\Services;

use App\Models\User;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\TwilioVerify;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    private const TTL_MINUTES = 5;

    private const MAX_VERIFY_ATTEMPTS = 5;

    private const MAX_SENDS_PER_10_MIN = 3;

    public function __construct(private SmsDriver $sms, private TwilioVerify $twilio) {}

    /** Silently does nothing when the per-phone send limit is hit (caller always answers the same). */
    public function send(User $user): void
    {
        RateLimiter::attempt('otp-send:'.$user->phone, self::MAX_SENDS_PER_10_MIN, function () use ($user) {
            $expiresAt = now()->addMinutes(self::TTL_MINUTES);

            if ($this->twilio->enabled()) { // Twilio holds the code; we keep only the attempt count and expiry
                if ($this->twilio->start($user->phone, $user->locale)) {
                    Cache::put($this->key($user->phone), ['twilio' => true, 'attempts' => 0, 'expires_at' => $expiresAt], $expiresAt);

                    return;
                }
                // Twilio refused (e.g. a trial account's unverified number): the phone gateway sends our own code
                // instead, when one is configured.
                if (blank(config('services.sms_gateway.url'))) {
                    return;
                }
            }

            $code = (string) random_int(100000, 999999);

            Cache::put($this->key($user->phone), [
                'hash' => $this->hash($code),
                'attempts' => 0,
                'expires_at' => $expiresAt,
            ], $expiresAt);

            $this->sms->send($user->phone, __('messages.otp_sms', ['code' => $code], $user->locale));
        }, 600);
    }

    public function verify(string $phone, string $code): bool
    {
        $key = $this->key($phone);
        $otp = Cache::get($key);

        if (! $otp || $otp['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
            return false;
        }

        $ok = isset($otp['twilio']) ? $this->twilio->check($phone, $code) : hash_equals($otp['hash'], $this->hash($code));

        if (! $ok) {
            $otp['attempts']++;
            Cache::put($key, $otp, $otp['expires_at']);

            return false;
        }

        Cache::forget($key);

        return true;
    }

    private function key(string $phone): string
    {
        return 'otp:'.$phone;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
