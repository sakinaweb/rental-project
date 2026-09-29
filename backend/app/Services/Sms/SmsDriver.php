<?php

namespace App\Services\Sms;

// Swappable SMS provider (MD §5 driver pattern). Bound in AppServiceProvider; OTP codes go through it.
interface SmsDriver
{
    public function send(string $to, string $message): void;
}
