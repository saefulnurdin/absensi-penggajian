<x-emails::layout>
    <h2 style="margin:0 0 6px;font-size:18px;color:#0f172a;">Slip Gaji & Rekap Absensi</h2>
    <p>Halo <strong>{{ $employee->name }}</strong>,</p>
    <p>Berikut rincian gaji Anda untuk periode <strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $payroll->period)->translatedFormat('F Y') }}</strong>.</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#64748b;">ID Pegawai</td><td style="padding:8px 12px;font-weight:600;">{{ $employee->employee_id }}</td></tr>
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#64748b;">Jabatan</td><td style="padding:8px 12px;font-weight:600;">{{ $employee->position }}</td></tr>
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#64748b;">Gaji Pokok</td><td style="padding:8px 12px;">Rp{{ number_format($payroll->base_salary, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#64748b;">Hari Kerja</td><td style="padding:8px 12px;">{{ $payroll->work_days }} hari (Hadir {{ $payroll->hadir_count }}, Izin {{ $payroll->izin_count }}, Tidak Hadir {{ $payroll->absent_count }})</td></tr>
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#dc2626;">Potongan Tidak Hadir</td><td style="padding:8px 12px;color:#dc2626;">- Rp{{ number_format($payroll->deduction, 0, ',', '.') }} <span style="color:#94a3b8;">({{ $payroll->absent_count }} &times; Rp{{ number_format($payroll->daily_salary, 0, ',', '.') }})</span></td></tr>
        <tr style="background:#eef2ff;"><td style="padding:10px 12px;color:#4338ca;font-weight:700;">GAJI BERSIH</td><td style="padding:10px 12px;color:#16a34a;font-weight:700;font-size:16px;">Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}</td></tr>
    </table>

    @if (count($recap['details']))
        <p style="margin-top:18px;font-size:13px;color:#475569;"><strong>Rekap kehadiran:</strong></p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:12px;">
            <tr style="background:#f8fafc;color:#64748b;">
                <td style="padding:6px 10px;font-weight:600;">Tanggal</td>
                <td style="padding:6px 10px;font-weight:600;">Jam</td>
                <td style="padding:6px 10px;font-weight:600;">Status</td>
            </tr>
            @foreach ($recap['details'] as $d)
                @php $statusColors = ['HADIR'=>'#16a34a','TERLAMBAT'=>'#d97706','IZIN'=>'#2563eb','TIDAK HADIR'=>'#dc2626','ABSENSI TIDAK LENGKAP'=>'#64748b']; @endphp
                <tr>
                    <td style="padding:5px 10px;border-top:1px solid #f1f5f9;">{{ \Carbon\Carbon::parse($d['date'])->translatedFormat('d M Y') }}</td>
                    <td style="padding:5px 10px;border-top:1px solid #f1f5f9;">{{ $d['time_in'] ? substr($d['time_in'],0,5).' - '.($d['time_out'] ? substr($d['time_out'],0,5) : '-') : '-' }}</td>
                    <td style="padding:5px 10px;border-top:1px solid #f1f5f9;color:{{ $statusColors[$d['status']] ?? '#0f172a' }};font-weight:600;">{{ $d['status'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="font-size:12px;color:#64748b;margin-top:16px;">Catatan: slip ini adalah prototype sederhana untuk tugas akhir dan bukan slip resmi perusahaan.</p>
</x-emails::layout>