<?php

namespace Tests\Feature;

use App\Services\WhatsApp\FonnteDriver;
use App\Services\WhatsApp\WahaDriver;
use App\Services\WhatsAppService;
use App\Support\WaNumber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_wa_number_normalization(): void
    {
        $this->assertSame('6285691406905', WaNumber::toInternational('085691406905'));
        $this->assertSame('6285691406905', WaNumber::toInternational('6285691406905'));
        $this->assertSame('6285691406905', WaNumber::toInternational('6285-6914-0690 5'));
        $this->assertNull(WaNumber::toInternational(''));
        $this->assertNull(WaNumber::toInternational(null));
        $this->assertNull(WaNumber::toInternational('abc'));
    }

    public function test_waha_driver_sends_normalized_chatid(): void
    {
        config([
            'whatsapp.driver' => 'waha',
            'whatsapp.waha.base_url' => 'http://127.0.0.2:3000',
            'whatsapp.waha.api_key' => 'rahasia',
            'whatsapp.waha.session' => 'sekali',
        ]);

        Http::fake([
            '*/api/sendText' => Http::response(['ok' => true], 200),
        ]);

        app(WhatsAppService::class)->send('085691406905', 'Halo tes');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://127.0.0.2:3000/api/sendText'
                && $request->header('X-Api-Key')[0] === 'rahasia'
                && $request['chatId'] === '6285691406905@c.us'
                && $request['session'] === 'sekali'
                && $request['text'] === 'Halo tes';
        });
    }

    public function test_waha_driver_throws_on_failure(): void
    {
        config([
            'whatsapp.driver' => 'waha',
            'whatsapp.waha.base_url' => 'http://127.0.0.2:3000',
            'whatsapp.waha.api_key' => 'rahasia',
        ]);

        Http::fake([
            '*/api/sendText' => Http::response('internal error', 500),
        ]);

        $this->expectException(RuntimeException::class);

        app(WhatsAppService::class)->send('085691406905', 'Halo');
    }

    public function test_fonnte_driver_uses_header_and_form_fields(): void
    {
        config([
            'whatsapp.driver' => 'fonnte',
            'whatsapp.fonnte.token' => 'FONNTE-TOKEN',
        ]);

        Http::fake([
            'api.fonnte.com/send' => Http::response(['status' => true], 200),
        ]);

        app(WhatsAppService::class)->send('085691406905', 'Pesan Fonnte');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->header('Authorization')[0] === 'FONNTE-TOKEN'
                && $request['target'] === '6285691406905'
                && $request['message'] === 'Pesan Fonnte';
        });
    }

    public function test_drivers_are_instantiable(): void
    {
        config(['whatsapp.driver' => 'log']);
        $this->assertInstanceOf(\App\Services\WhatsApp\LogDriver::class, app(WhatsAppService::class)->driver());

        config(['whatsapp.driver' => 'waha']);
        $this->assertInstanceOf(WahaDriver::class, app(WhatsAppService::class)->driver());

        config(['whatsapp.driver' => 'fonnte']);
        $this->assertInstanceOf(FonnteDriver::class, app(WhatsAppService::class)->driver());
    }
}