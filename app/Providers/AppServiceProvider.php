<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Memungkinkan <x-emails::layout> menunjuk ke resources/views/emails/layout.blade.php
        Blade::anonymousComponentNamespace('emails', 'emails');

        // Pagination custom agar konsisten dengan tema lokal.
        Paginator::defaultView('pagination.custom');
        Paginator::defaultSimpleView('pagination.custom');

        // Cloudflare Tunnel menggunakan HTTPS di sisi publik.
        // if (app()->environment('local')) {
        //     URL::forceScheme('https');
        // }
    }
}
