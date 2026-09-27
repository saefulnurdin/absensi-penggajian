@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 13V6a2 2 0 0 1 4 0v7"/><path d="M9 13v-2"/><circle cx="9" cy="15" r="1"/>
                        <path d="M15 8a8 8 0 0 0-12 0"/><circle cx="17" cy="12" r="5"/>
                    </svg>
                </div>
                <div class="brand">
                    <strong>Absensi RFID</strong>
                    <small>Prototype</small>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">Menu Utama</div>
                <a href="{{ route('dashboard') }}" class="nav-link @if(request()->routeIs('dashboard')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-dashboard"/></svg> Dashboard
                </a>

                <div class="nav-section">Master Data</div>
                <a href="{{ route('employees.index') }}" class="nav-link @if(request()->routeIs('employees.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-users"/></svg> Pegawai
                </a>
                <a href="{{ route('rfid-cards.index') }}" class="nav-link @if(request()->routeIs('rfid-cards.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-rfid"/></svg> RFID Card
                </a>
                <a href="{{ route('work-days.index') }}" class="nav-link @if(request()->routeIs('work-days.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-calendar-grid"/></svg> Hari Kerja
                </a>
                <a href="{{ route('shifts.index') }}" class="nav-link @if(request()->routeIs('shifts.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg> Shift Kerja
                </a>

                <div class="nav-section">Absensi</div>
                <a href="{{ route('attendance.index') }}" class="nav-link @if(request()->routeIs('attendance.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg> Data Absensi
                </a>
                <a href="{{ route('rekap.index') }}" class="nav-link @if(request()->routeIs('rekap.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg> Rekap Absensi
                </a>
                <a href="{{ route('calendar.index') }}" class="nav-link @if(request()->routeIs('calendar.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-calendar"/></svg> Kalender Absensi
                </a>

                <div class="nav-section">Penggajian</div>
                <a href="{{ route('payroll.index') }}" class="nav-link @if(request()->routeIs('payroll.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-wallet"/></svg> Data Gaji
                </a>
                <a href="{{ route('kasbon.index') }}" class="nav-link @if(request()->routeIs('kasbon.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-calculator"/></svg> Kasbon
                </a>

                <div class="nav-section">Sistem</div>
                <a href="{{ route('devices.index') }}" class="nav-link @if(request()->routeIs('devices.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-device"/></svg> Perangkat ESP32
                </a>
                <a href="{{ route('email-logs.index') }}" class="nav-link @if(request()->routeIs('email-logs.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-mail"/></svg> Log Email
                </a>
                <a href="{{ route('whatsapp-logs.index') }}" class="nav-link @if(request()->routeIs('whatsapp-logs.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-send"/></svg> Log WhatsApp
                </a>
                <a href="{{ route('settings.index') }}" class="nav-link @if(request()->routeIs('settings.*')) active @endif">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-settings"/></svg> Pengaturan
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="version-chip">v1.0 - Prototype </div>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <button type="button" class="icon-btn menu-btn" id="sidebar-toggle" aria-label="Menu">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-menu"/></svg>
                </button>
                <div class="page-title">{{ $title }}</div>
                <div class="topbar-spacer"></div>
                <button type="button" class="icon-btn" id="theme-toggle" aria-label="Ganti tema"></button>
                <div class="user-chip">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    {{ auth()->user()->name }}
                </div>
                <form action="{{ route('logout') }}" method="POST" class="logout-form">
                    @csrf
                    <button type="submit" class="logout-btn" title="Keluar">
                        <svg><use href="{{ asset('img/icons.svg') }}#i-logout"/></svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </header>

            <main class="content">
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>

    <button type="button" class="to-top" id="to-top" aria-label="Ke atas">
        <svg><use href="{{ asset('img/icons.svg') }}#i-arrow-up"/></svg>
    </button>

    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>