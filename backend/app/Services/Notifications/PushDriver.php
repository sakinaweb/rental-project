<?php

namespace App\Services\Notifications;

// Swappable push provider (MD §5 driver pattern). Returns false when the token is no longer valid, so it can be removed.
interface PushDriver
{
    /** @param  array<string, string>  $data */
    public function send(string $token, string $title, string $body, array $data = []): bool;
}
