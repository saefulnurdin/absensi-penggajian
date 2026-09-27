<?php

namespace App\Services;

use App\Support\WaNumber;
use App\Services\WhatsApp\Contracts\WhatsAppDriver;
use App\Services\WhatsApp\FonnteDriver;
use App\Services\WhatsApp\LogDriver;
use App\Services\WhatsApp\WahaDriver;

class WhatsAppService
{
    public function driver(): WhatsAppDriver
    {
        return match (config('whatsapp.driver', 'log')) {
            'waha' => new WahaDriver((array) config('whatsapp.waha')),
            'fonnte' => new FonnteDriver((array) config('whatsapp.fonnte')),
            default => new LogDriver,
        };
    }

    /**
     * Kirim pesan WA. Nomor diubah otomatis ke internasional (08xxx -> 628xxx).
     */
    public function send(string $phone, string $text): void
    {
        $recipient = WaNumber::toInternational($phone);

        if ($recipient === null) {
            throw new \InvalidArgumentException('Nomor WhatsApp tidak valid: '.$phone);
        }

        $this->driver()->send($recipient, $text);
    }

    public function isEnabled(): bool
    {
        if (config('whatsapp.driver') === 'waha') {
            return config('whatsapp.waha.api_key') !== '';
        }

        if (config('whatsapp.driver') === 'fonnte') {
            return config('whatsapp.fonnte.token') !== '';
        }

        return true;
    }
}