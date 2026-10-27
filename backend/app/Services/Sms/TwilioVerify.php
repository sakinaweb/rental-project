<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// OTP by Twilio Verify: Twilio creates, sends and checks the code. On when all three TWILIO_* keys are set;
// OtpService still applies its own send limits and wrong-code lock in front of it.
class TwilioVerify
{
    public function enabled(): bool
    {
        return filled(config('services.twilio.account_sid')) && filled(config('services.twilio.auth_token')) && filled(config('services.twilio.verify_sid'));
    }

    /** Sends the code by SMS. A refusal (e.g. a trial account's unverified number) is logged, not shown. */
    public function start(string $phone, string $locale): void
    {
        $response = $this->http()->post('Verifications', ['To' => $phone, 'Channel' => 'sms', 'Locale' => $locale]);

        if ($response->failed()) {
            Log::warning('Twilio Verify send failed', ['status' => $response->status(), 'code' => $response->json('code'), 'message' => $response->json('message')]);
        }
    }

    /** Twilio answers 404 once the verification is approved, expired or out of attempts. */
    public function check(string $phone, string $code): bool
    {
        $response = $this->http()->post('VerificationCheck', ['To' => $phone, 'Code' => $code]);

        if ($response->serverError()) {
            Log::warning('Twilio Verify check failed', ['status' => $response->status()]);
        }

        return $response->successful() && $response->json('status') === 'approved';
    }

    private function http(): PendingRequest
    {
        return Http::asForm()->timeout(10)
            ->withBasicAuth((string) config('services.twilio.account_sid'), (string) config('services.twilio.auth_token'))
            ->baseUrl('https://verify.twilio.com/v2/Services/'.config('services.twilio.verify_sid').'/');
    }
}
