<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Contracts\WhatsAppDriver;
use Illuminate\Support\Facades\Log;

final class LogDriver implements WhatsAppDriver
{
    public function send(string $recipient, string $text): void
    {
        Log::info('WhatsApp [log-driver]', [
            'recipient' => $recipient,
            'text' => $text,
        ]);
    }
}