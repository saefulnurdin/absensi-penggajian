<?php

namespace App\Services\WhatsApp\Contracts;

interface WhatsAppDriver
{
    /**
     * Kirim pesan teks. $recipient dalam format internasional 628xxxxxxxxxx.
     *
     * @throws \RuntimeException saat pengiriman gagal
     */
    public function send(string $recipient, string $text): void;
}