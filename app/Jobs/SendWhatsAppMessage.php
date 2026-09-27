<?php

namespace App\Jobs;

use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use App\Support\WaNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public ?string $recipient,
        public string $text,
        public string $type,
        public ?string $relatedType = null,
        public ?int $relatedId = null,
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $phone = WaNumber::toInternational($this->recipient);

        if ($phone === null) {
            WhatsAppLog::create([
                'type' => $this->type,
                'recipient' => $this->recipient,
                'body_preview' => 'SKIPPED: nomor WhatsApp kosong/valid',
                'status' => 'SKIPPED',
                'related_type' => $this->relatedType,
                'related_id' => $this->relatedId,
            ]);

            Log::warning('WhatsApp skip nomor kosong', ['type' => $this->type]);

            return;
        }

        try {
            $whatsapp->send($phone, $this->text);

            WhatsAppLog::create([
                'type' => $this->type,
                'recipient' => $phone,
                'body_preview' => mb_substr($this->text, 0, 200),
                'status' => 'SENT',
                'related_type' => $this->relatedType,
                'related_id' => $this->relatedId,
            ]);
        } catch (\Throwable $e) {
            WhatsAppLog::create([
                'type' => $this->type,
                'recipient' => $phone,
                'body_preview' => 'Kesalahan: '.$e->getMessage(),
                'status' => 'FAILED',
                'related_type' => $this->relatedType,
                'related_id' => $this->relatedId,
            ]);

            throw $e;
        }
    }
}