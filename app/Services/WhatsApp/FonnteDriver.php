<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Contracts\WhatsAppDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FonnteDriver implements WhatsAppDriver
{
    public function __construct(private array $config) {}

    public function send(string $recipient, string $text): void
    {
        $response = Http::timeout(10)
            ->withHeaders(['Authorization' => $this->config['token']])
            ->asForm()
            ->post('https://api.fonnte.com/send', [
                'target' => $recipient,
                'message' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Fonnte error '.$response->status().': '.$response->body(),
            );
        }
    }
}