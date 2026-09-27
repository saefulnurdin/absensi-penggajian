<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver WhatsApp
    |--------------------------------------------------------------------------
    |
    | waha  : self-hosted WAHA (Docker), gratis untuk teks, tanpa watermark.
    | fonnte: API cloud Fonnte (gratis dengan watermark, berbayar tanpa).
    | log   : hanya menulis ke log (untuk testing / mode tenang).
    |
    */

    'driver' => env('WA_DRIVER', 'log'),

    'waha' => [
        'base_url' => env('WAHA_BASE_URL', 'http://127.0.0.1:3000'),
        'api_key' => env('WAHA_API_KEY', ''),
        'session' => env('WAHA_SESSION', 'default'),
    ],

    'fonnte' => [
        'token' => env('FONNTE_TOKEN', ''),
    ],

];