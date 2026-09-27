<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Gaji {{ $payroll->employee->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .slip-wrap { max-width: 520px; margin: 0 auto; padding: 30px 16px; }
        .slip {
            border: 1.5px solid var(--text); border-radius: 12px; overflow: hidden;
            background: var(--surface);
        }
        .slip-head { text-align: center; padding: 18px; border-bottom: 2px dashed var(--border); }
        .slip-head h1 { font-size: 20px; letter-spacing: .12em; margin: 0 0 4px; }
        .slip-head .company { font-size: 14px; font-weight: 600; }
        .slip-head .period { font-size: 13px; color: var(--text-muted); }
        .slip-body { padding: 18px 22px; }
        .slip-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .slip-table td { padding: 7px 0; border-bottom: 1px dotted var(--border); vertical-align: top; }
        .slip-table td:last-child { text-align: right; font-weight: 600; }
        .slip-total { background: var(--primary-50); }
        .slip-total td { border-bottom: 0; padding-top: 12px; }
        .slip-note { font-size: 12px; color: var(--text-muted); text-align: center; padding: 12px; border-top: 2px dashed var(--border); }
        @media print {
            body { background: #fff; }
        }
        @media (max-width: 760px) { .slip-wrap { padding: 8px; } }
    </style>
</head>
<body>
    <div class="slip-wrap">
        <div class="no-print" style="text-align:center;margin-bottom:14px">
            <button class="btn btn-primary" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak Slip Gaji
            </button>
        </div>

        <div class="slip">
            <div class="slip-head">
                <h1>SLIP GAJI</h1>
                <div class="company">{{ $payroll->employee->position }}</div>
                <div class="company" style="color:var(--primary-600)">{{ config('app.name') }}</div>
                <div class="period">Periode: {{ \Carbon\Carbon::createFromFormat('Y-m', $payroll->period)->translatedFormat('F Y') }}</div>
            </div>
            <div class="slip-body">
                <table class="slip-table">
                    <tr><td>Nama</td><td>{{ $payroll->employee->name }}</td></tr>
                    <tr><td>ID Pegawai</td><td>{{ $payroll->employee->employee_id }}</td></tr>
                    <tr><td>Jabatan</td><td>{{ $payroll->employee->position }}</td></tr>
                    <tr><td>Hari Kerja</td><td>{{ $payroll->work_days }} hari</td></tr>
                    <tr><td>Status Kehadiran</td><td>{{ $payroll->hadir_count }} hadir &bull; {{ $payroll->izin_count }} izin &bull; {{ $payroll->absent_count }} tidak hadir</td></tr>
                    <tr><td></td><td></td></tr>
                    <tr><td>Gaji Pokok</td><td>Rp{{ number_format($payroll->base_salary, 0, ',', '.') }}</td></tr>
                    <tr><td style="color:var(--danger)">Potongan Tidak Hadir <span style="font-weight:400;color:var(--text-muted)">({{ $payroll->absent_count }} &times; Rp{{ number_format($payroll->daily_salary, 0, ',', '.') }})</span></td>
                        <td style="color:var(--danger)">- Rp{{ number_format($payroll->deduction, 0, ',', '.') }}</td></tr>
                    @if ($payroll->kasbon_deduction > 0)
                        <tr><td style="color:var(--warning)">Potongan Kasbon</td>
                            <td style="color:var(--warning)">- Rp{{ number_format($payroll->kasbon_deduction, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="slip-total">
                        <td><strong>GAJI BERSIH</strong></td>
                        <td style="font-size:18px;color:var(--success)"><strong>Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}</strong></td>
                    </tr>
                </table>
            </div>
            <div class="slip-note">Prototype sistem penggajian &bull; Tugas Akhir &bull; Dihasilkan {{ now()->translatedFormat('d F Y H:i') }}</div>
        </div>

        @if (isset($recap) && count($recap['details']))
            <div style="margin-top:20px" class="no-print">
                <div class="card" style="padding:0">
                    <div class="table-wrap">
                        <table class="slip-table" style="font-size:12px">
                            <thead>
                                <tr><th>Tanggal</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($recap['details'] as $d)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($d['date'])->translatedFormat('d M') }}</td>
                                        <td><span class="badge {{ \App\Models\Attendance::statusBadgeClassFor($d['status']) }}">{{ $d['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</body>
</html>