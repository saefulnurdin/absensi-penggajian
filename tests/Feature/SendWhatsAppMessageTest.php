<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_sent_log_with_log_driver(): void
    {
        config(['whatsapp.driver' => 'log']);

        (new SendWhatsAppMessage('085691406905', 'Halo tes', 'uji-wa'))->handle(app(WhatsAppService::class));

        $this->assertDatabaseHas('whatsapp_logs', [
            'type' => 'uji-wa',
            'recipient' => '6285691406905',
            'status' => 'SENT',
        ]);
    }

    public function test_job_skips_when_phone_missing(): void
    {
        config(['whatsapp.driver' => 'log']);

        (new SendWhatsAppMessage('', 'Halo tes', 'uji-wa'))->handle(app(WhatsAppService::class));

        $this->assertDatabaseHas('whatsapp_logs', [
            'type' => 'uji-wa',
            'status' => 'SKIPPED',
        ]);
    }

    public function test_job_normalizes_phone_before_send(): void
    {
        config(['whatsapp.driver' => 'log']);

        (new SendWhatsAppMessage('+62 856 9140 6905', 'Halo', 'uji-wa'))->handle(app(WhatsAppService::class));

        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient' => '6285691406905',
            'status' => 'SENT',
        ]);
    }
}