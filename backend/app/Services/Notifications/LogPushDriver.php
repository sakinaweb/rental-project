<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

// Default until Firebase credentials are configured: writes the push to the log.
class LogPushDriver implements PushDriver
{
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        Log::info('Push', ['token' => substr($token, 0, 12).'…', 'title' => $title, 'body' => $body, 'data' => $data]);

        return true;
    }
}
