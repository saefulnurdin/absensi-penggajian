<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Contracts\WhatsAppDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class WahaDriver implements WhatsAppDriver
{
    public function __construct(private array $config) {}

    public function send(string $recipient, string $text): void
    {
        $response = $this->client()->post(
            rtrim($this->config['base_url'], '/').'/api/sendText',
            [
                'chatId' => $recipient.'@c.us',
                'text' => $text,
                'session' => $this->config['session'],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'WAHA error '.$response->status().': '.$response->body(),
            );
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout(10)
            ->withHeaders([
                'X-Api-Key' => $this->config['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->asJson();
    }
}