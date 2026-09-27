<x-emails::layout>
    <h2 style="margin:0 0 6px;font-size:18px;color:#0f172a;">
        <?php
            $titles = [
                'hadir' => 'Konfirmasi Absensi Masuk',
                'terlambat' => 'Pemberitahuan Keterlambatan',
                'pulang' => 'Konfirmasi Absensi Pulang',
                'izin' => 'Pemberitahuan Izin',
                'tidak_hadir' => 'Pemberitahuan Tidak Masuk',
            ];
        ?>
        {{ $titles[$event] ?? 'Notifikasi Absensi' }}
    </h2>
    <p>Halo <strong>{{ $employee->name }}</strong>,</p>

    @if ($event === 'hadir')
        <p>Absensi masuk Anda telah tercatat pada <strong>{{ $date ? \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') : now()->translatedFormat('l, d F Y') }}</strong> pukul <strong style="color:#16a34a">{{ $time ? substr($time,0,5) : '-' }}</strong> dengan status <strong style="color:#16a34a">HADIR</strong>.</p>
    @elseif ($event === 'terlambat')
        <p>Anda tercatat hadir pada pukul <strong style="color:#d97706">{{ $time ? substr($time,0,5) : '-' }}</strong> dan dinyatakan <strong style="color:#d97706">TERLAMBAT {{ $lateMinutes }} menit</strong>.</p>
        <p style="font-size:12px;color:#64748b;">Keterlambatan dicatat sebagai informasi pada prototype ini dan tidak memotong gaji.</p>
    @elseif ($event === 'pulang')
        <p>Absensi pulang Anda telah tercatat pada pukul <strong style="color:#16a34a">{{ $time ? substr($time,0,5) : '-' }}</strong>. Terima kasih!</p>
    @elseif ($event === 'izin')
        <p>Status <strong style="color:#2563eb">IZIN</strong> Anda telah tercatat pada <strong>{{ $date ? \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') : '-' }}</strong>.</p>
    @elseif ($event === 'tidak_hadir')
        <p>Kami tidak menemukan catatan absensi Anda pada hari kerja <strong>{{ $date ? \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') : '-' }}</strong>.</p>
        <p>Mohon hubungi admin jika ini merupakan kesalahan.</p>
    @endif

    @if ($customMessage)
        <p style="background:#eef2ff;border-left:3px solid #4f46e5;padding:10px 12px;font-size:13px;color:#4338ca;">{{ $customMessage }}</p>
    @endif

    <p style="font-size:12px;color:#64748b;margin-top:22px;">ID Pegawai: <strong>{{ $employee->employee_id }}</strong></p>
</x-emails::layout>