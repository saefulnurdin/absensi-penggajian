<x-layout title="Dashboard">
    <div class="page-head">
        <div>
            <h1>Selamat Datang, {{ auth()->user()->name }}</h1>
            <div class="sub">{{ $company }} &bull; {{ now()->translatedFormat('l, d F Y') }} &bull; {{ now()->format('H:i') }} WIB</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg> Tambah Absensi
            </a>
            <a href="{{ route('rekap.index') }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg> Lihat Rekap
            </a>
        </div>
    </div>

    <div class="grid grid-5" style="margin-bottom:16px">
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--primary-50);color:var(--primary-700)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-users"/></svg>
            </div>
            <div>
                <div class="stat-label">Total Pegawai Aktif</div>
                <div class="stat-value">{{ $stats['total_employees'] }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--success-bg);color:var(--success)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg>
            </div>
            <div>
                <div class="stat-label">Hadir Hari Ini</div>
                <div class="stat-value">{{ $stats['hadir'] }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--warning-bg);color:var(--warning)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg>
            </div>
            <div>
                <div class="stat-label">Terlambat Hari Ini</div>
                <div class="stat-value">{{ $stats['terlambat'] }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--info-bg);color:var(--info)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg>
            </div>
            <div>
                <div class="stat-label">Izin Hari Ini</div>
                <div class="stat-value">{{ $stats['izin'] }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--danger-bg);color:var(--danger)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
            </div>
            <div>
                <div class="stat-label">Tidak Hadir Hari Ini</div>
                <div class="stat-value">{{ $stats['tidak_hadir'] }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg>
                Absensi Terbaru Hari Ini
            </div>

            @if ($recent->count() === 0)
                <div class="empty-state">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg>
                    <div>Belum ada absensi tercatat hari ini.</div>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pegawai</th>
                                <th>Status</th>
                                <th>Sumber</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $row)
                                <tr>
                                    <td>{{ $row->time_in ?? $row->time_out }}</td>
                                    <td>
                                        <strong>{{ $row->employee->name }}</strong>
                                        <div style="color:var(--text-muted);font-size:12px">{{ $row->employee->employee_id }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $row->statusBadgeClass() }}">
                                            <span class="badge-dot"></span> {{ $row->status }}
                                        </span>
                                    </td>
                                    <td><span class="badge badge-muted">{{ $row->source }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-calendar"/></svg>
                Ringkasan Hari Ini
            </div>

            @php
                $total = array_sum($summary->all()) ?: 1;
                $bars = [
                    ['Hadir', $summary->get('HADIR', 0), 'var(--success)', 'var(--success-bg)'],
                    ['Terlambat', $summary->get('TERLAMBAT', 0), 'var(--warning)', 'var(--warning-bg)'],
                    ['Izin', $summary->get('IZIN', 0), 'var(--info)', 'var(--info-bg)'],
                    ['Tidak Hadir', $stats['tidak_hadir'], 'var(--danger)', 'var(--danger-bg)'],
                ];
            @endphp

            @foreach ($bars as [$label, $value, $color, $bg])
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px">
                        <span style="color:var(--text-soft);font-weight:600">{{ $label }}</span>
                        <span style="color:var(--text-muted)">{{ $value }} orang</span>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:{{ round(($value / $total) * 100) }}%;background:{{ $color }}"></div>
                    </div>
                </div>
            @endforeach

            <div style="margin-top:14px;border-top:1px dashed var(--border);padding-top:12px;font-size:12px;color:var(--text-muted)">
                Persentase dihitung dari seluruh pegawai aktif. Data diperbarui otomatis saat halaman dimuat.
            </div>
        </div>
    </div>
</x-layout>
