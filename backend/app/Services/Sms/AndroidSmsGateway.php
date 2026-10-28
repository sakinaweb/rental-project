<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// SMS from your own Android phone with the free "SMS Gateway for Android" app (sms-gate.app): the phone sends each
// OTP as a normal SMS from its SIM. SMS_GATEWAY_URL is the app's local address (http://<phone-ip>:8080/message, same
// network as the server) or its cloud relay (https://api.sms-gate.app/3rdparty/v1/message).
class AndroidSmsGateway implements SmsDriver
{
    /** A refusal or an unreachable phone is logged (without the text, which holds the code), never shown to the user. */
    public function send(string $to, string $message): void
    {
        try {
            $response = Http::timeout(10)
                ->withBasicAuth((string) config('services.sms_gateway.username'), (string) config('services.sms_gateway.password'))
                ->post((string) config('services.sms_gateway.url'), ['textMessage' => ['text' => $message], 'phoneNumbers' => [$to]]);

            if ($response->failed()) {
                Log::warning('SMS gateway send failed', ['status' => $response->status()]);
            }
        } catch (ConnectionException $e) {
            Log::warning('SMS gateway unreachable', ['error' => $e->getMessage()]);
        }
    }
}
