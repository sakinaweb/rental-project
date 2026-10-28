<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// SMS from your own Android phone: the phone sends each OTP as a normal SMS from its SIM. Two gateway apps work:
// - "SMS Gateway for Android" (sms-gate.app): SMS_GATEWAY_URL http://<phone-ip>:8080/message or its cloud relay
//   https://api.sms-gate.app/3rdparty/v1/message, with SMS_GATEWAY_USERNAME / SMS_GATEWAY_PASSWORD.
// - "SMS Gateway Free" (open-source NanoHTTPD app): SMS_GATEWAY_URL http://<phone-ip>:8080/api/send with
//   SMS_GATEWAY_API_KEY. A set API key selects this format.
class AndroidSmsGateway implements SmsDriver
{
    /** A refusal or an unreachable phone is logged (without the text, which holds the code), never shown to the user. */
    public function send(string $to, string $message): void
    {
        $url = (string) config('services.sms_gateway.url');
        $apiKey = config('services.sms_gateway.api_key');

        try {
            $response = filled($apiKey)
                ? Http::timeout(10)->withHeaders(['X-API-Key' => $apiKey])->post($url, ['phone_number' => $to, 'message' => $message])
                : Http::timeout(10)
                    ->withBasicAuth((string) config('services.sms_gateway.username'), (string) config('services.sms_gateway.password'))
                    ->post($url, ['textMessage' => ['text' => $message], 'phoneNumbers' => [$to]]);

            if ($response->failed()) {
                Log::warning('SMS gateway send failed', ['status' => $response->status()]);
            }
        } catch (ConnectionException $e) {
            Log::warning('SMS gateway unreachable', ['error' => $e->getMessage()]);
        }
    }
}
