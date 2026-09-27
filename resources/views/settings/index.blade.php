<x-layout title="Pengaturan Sistem">
    <div class="page-head">
        <div>
            <h1>Pengaturan Sistem</h1>
            <div class="sub">Jam kerja, istirahat, dan toleransi keterlambatan — semua dikonfigurasi dari database</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card card-pad">
            <form method="POST" action="{{ route('settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card-title">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-settings"/></svg>
                    Jam Kerja & Aturan Absensi
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="company_name">Nama Perusahaan</label>
                        <input type="text" id="company_name" name="company_name" value="{{ $statuses['company_name'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="late_tolerance">Toleransi Keterlambatan (menit)</label>
                        <input type="number" id="late_tolerance" name="late_tolerance" min="0" max="180" value="{{ $statuses['late_tolerance'] }}" required>
                        <div class="form-help">Contoh: 15 → tap jam 08:12 tetap HADIR, catat keterlambatan 12 menit.</div>
                    </div>

                    <div class="form-group">
                        <label for="wa_admin_phone">Nomor WhatsApp Admin (Rekap Harian)</label>
                        <input type="text" id="wa_admin_phone" name="wa_admin_phone" value="{{ $statuses['wa_admin_phone'] }}"
                               placeholder="08xxxxxxxxxx">
                        <div class="form-help">Rekap kehadiran harian dikirim ke nomor ini via WhatsApp.</div>
                    </div>
                    <div class="form-group">
                        <label for="wa_recap_time">Jam Kirim Rekap Harian</label>
                        <input type="time" id="wa_recap_time" name="wa_recap_time" value="{{ $statuses['wa_recap_time'] }}">
                        <div class="form-help">Scheduler memeriksa setiap 30 menit dan mengirim bila jam cocok.</div>
                    </div>
                    <div class="form-group">
                        <label for="work_start">Jam Mulai Kerja</label>
                        <input type="time" id="work_start" name="work_start" value="{{ $statuses['work_start'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="work_end">Jam Selesai Kerja</label>
                        <input type="time" id="work_end" name="work_end" value="{{ $statuses['work_end'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="break_start">Jam Istirahat Mulai</label>
                        <input type="time" id="break_start" name="break_start" value="{{ $statuses['break_start'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="break_end">Jam Istirahat Selesai</label>
                        <input type="time" id="break_end" name="break_end" value="{{ $statuses['break_end'] }}" required>
                    </div>
                </div>

                <div class="alert alert-info" style="margin:16px 0 0">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
                    <div>
                        Perubahan langsung berlaku untuk perhitungan keterlambatan berikutnya. Data absensi lama tidak diubah ulang.
                    </div>
                </div>

                <div class="form-actions" style="margin-top:16px">
                    <button type="submit" class="btn btn-primary">
                        <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg> Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>

        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg>
                Ringkasan Aturan Aktif
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr><td><strong>Perusahaan</strong></td><td>{{ $statuses['company_name'] }}</td></tr>
                        <tr><td><strong>Jam kerja</strong></td><td>{{ $statuses['work_start'] }} - {{ $statuses['work_end'] }}</td></tr>
                        <tr><td><strong>Istirahat</strong></td><td>{{ $statuses['break_start'] }} - {{ $statuses['break_end'] }}</td></tr>
                        <tr><td><strong>Toleransi terlambat</strong></td><td>{{ $statuses['late_tolerance'] }} menit</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card-title" style="margin-top:20px">
                <svg><use href="{{ asset('img/icons.svg') }}#i-rfid"/></svg>
                Konfigurasi Perangkat
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr>
                            <td><strong>API Key ESP32</strong></td>
                            <td>
                                <code style="word-break:break-all">{{ config('app.api_key') }}</code>
                                <div class="form-help">Kirim sebagai header <code>X-API-KEY</code>.</div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>URL API</strong></td>
                            <td><code>{{ url('/api/attendance') }}</code></td>
                        </tr>
                        <tr>
                            <td><strong>Timezone</strong></td>
                            <td>Asia/Jakarta (WIB)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-title" style="margin-top:20px">
                <svg><use href="{{ asset('img/icons.svg') }}#i-send"/></svg>
                WhatsApp
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr>
                            <td><strong>Driver aktif</strong></td>
                            <td><code>{{ config('whatsapp.driver', 'log') }}</code></td>
                        </tr>
                        <tr>
                            <td><strong>Nomor admin</strong></td>
                            <td>{{ blank($statuses['wa_admin_phone']) ? 'Belum diatur' : $statuses['wa_admin_phone'] }}</td>
                        </tr>
                        <tr>
                            <td><strong>Jam rekap</strong></td>
                            <td>{{ $statuses['wa_recap_time'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layout>