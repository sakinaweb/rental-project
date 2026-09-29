<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

// Default driver: writes the SMS to the log so OTP works before a real provider is configured.
class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS', ['to' => $to, 'message' => $message]);
    }
}
