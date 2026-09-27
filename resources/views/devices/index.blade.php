<x-layout title="Perangkat ESP32">
    <div class="page-head">
        <div>
            <h1>Status Perangkat ESP32</h1>
            <div class="sub">Pantau heartbeat dan koneksi perangkat absensi</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('settings.index') }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-settings"/></svg> Lihat API Key
            </a>
        </div>
    </div>

    @forelse ($devices as $device)
        <div class="card" style="margin-bottom:14px">
            <div class="card-pad" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <div class="stat-icon" style="width:52px;height:52px;background:var(--surface-3);color:var(--text-soft)">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-device"/></svg>
                </div>
                <div style="flex:1;min-width:200px">
                    <h2 style="margin:0">{{ $device->device_name }}</h2>
                    <div style="color:var(--text-muted);font-size:13px">
                        IP {{ $device->ip_address ?? '-' }} &bull; Update terakhir: {{ $device->last_seen_at?->diffForHumans() ?? '-' }}
                    </div>
                </div>
                <div>
                    <span class="badge {{ $device->online ? 'badge-success' : 'badge-danger' }}">
                        <span class="badge-dot"></span> {{ $device->online ? 'Online' : 'Offline' }}
                    </span>
                    @if ($device->last_seen_at)
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;text-align:center">
                            {{ $device->last_seen_at->translatedFormat('d M Y H:i:s') }}
                        </div>
                    @endif
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--text-soft)">
                    @if ($device->power_source)
                        <span class="badge {{ $device->power_source === 'MAINS' ? 'badge-success' : 'badge-warning' }}">
                            <span class="badge-dot"></span>
                            {{ $device->power_source === 'MAINS' ? 'Daya Utama' : 'Baterai' }}
                            @if ($device->battery_percent !== null)
                                {{ $device->battery_percent.'%' }}
                            @endif
                        </span>
                    @endif
                    @if ($device->wifi_rssi !== null)
                        <span class="badge badge-info">
                            <svg style="width:13px;height:13px"><use href="{{ asset('img/icons.svg') }}#i-wifi"/></svg>
                            WiFi {{ $device->wifi_rssi }} dBm
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="empty-state">
                <svg><use href="{{ asset('img/icons.svg') }}#i-device"/></svg>
                <div>Belum ada heartbeat dari perangkat. Hubungkan ESP32 lalu panggil<br>
                    <code>POST {{ url('/api/device/heartbeat') }}</code> dengan header <code>X-API-KEY</code>.</div>
            </div>
        </div>
    @endforelse

    <div class="card card-pad" style="margin-top:18px">
        <div class="card-title">
            <svg><use href="{{ asset('img/icons.svg') }}#i-wifi"/></svg>
            Cara Menghubungkan ESP32
        </div>
        <ol style="margin:0;padding-left:20px;color:var(--text-soft);line-height:2">
            <li>Hubungkan ESP32 ke jaringan WiFi yang sama dengan server.</li>
            <li>Kirim heartbeat tiap 30 detik: <code>POST {{ url('/api/device/heartbeat') }}</code></li>
            <li>Kirim absensi: <code>POST {{ url('/api/attendance') }}</code> dengan body <code>{"uid":"A1B2C3D4"}</code></li>
            <li>Sertakan header <code>X-API-KEY: {{ config('app.api_key') }}</code> pada semua request.</li>
        </ol>
    </div>
</x-layout>