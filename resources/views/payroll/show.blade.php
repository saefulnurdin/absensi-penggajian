<x-layout title="Rincian Gaji">
    <div class="page-head">
        <div>
            <h1>Rincian Gaji</h1>
            <div class="sub">{{ $payroll->employee->name }} &bull; Periode {{ \Carbon\Carbon::createFromFormat('Y-m', $payroll->period)->translatedFormat('F Y') }}</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('payroll.index', ['period' => $payroll->period]) }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-chev-left"/></svg> Kembali
            </a>
            <a href="{{ route('payroll.slip', $payroll) }}" class="btn btn-primary no-print" target="_blank">
                <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg> Lihat Slip Gaji
            </a>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-wallet"/></svg>
                Komponen Gaji
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr><td><strong>ID Pegawai</strong></td><td>{{ $payroll->employee->employee_id }}</td></tr>
                        <tr><td><strong>Nama</strong></td><td>{{ $payroll->employee->name }}</td></tr>
                        <tr><td><strong>Jabatan</strong></td><td>{{ $payroll->employee->position }}</td></tr>
                        <tr><td><strong>Periode</strong></td><td>{{ \Carbon\Carbon::createFromFormat('Y-m', $payroll->period)->translatedFormat('F Y') }}</td></tr>
                        <tr><td><strong>Gaji Pokok</strong></td><td>Rp{{ number_format($payroll->base_salary, 0, ',', '.') }}</td></tr>
                        <tr><td><strong>Jumlah Hari Kerja</strong></td><td>{{ $payroll->work_days }} hari</td></tr>
                        <tr><td><strong>Gaji Harian</strong></td><td>Rp{{ number_format($payroll->daily_salary, 0, ',', '.') }}</td></tr>
                        <tr>
                            <td><strong>Potongan Tidak Hadir</strong></td>
                            <td style="color:var(--danger)">
                                - Rp{{ number_format($payroll->deduction, 0, ',', '.') }}
                                <span style="color:var(--text-muted);font-size:12px">
                                    ({{ $payroll->absent_count }} hari &times; Rp{{ number_format($payroll->daily_salary, 0, ',', '.') }})
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Potongan Kasbon</strong></td>
                            <td style="color:var(--warning)">
                                - Rp{{ number_format($payroll->kasbon_deduction, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td><strong>GAJI BERSIH</strong></td>
                            <td style="font-size:18px;font-weight:700;color:var(--success)">
                                Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr><td><strong>Status</strong></td><td>
                            <span class="badge {{ $payroll->status === 'DIPROSES' ? 'badge-success' : 'badge-muted' }}">{{ $payroll->status }}</span>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-calendar"/></svg>
                Statistik Kehadiran
            </div>
            <div class="grid grid-2" style="margin-bottom:14px">
                <div class="stat-card" style="padding:12px;border:1px solid var(--border);border-radius:8px">
                    <div class="stat-label">Hadir (termasuk terlambat)</div>
                    <div class="stat-value" style="font-size:18px;color:var(--success)">{{ $payroll->hadir_count }}</div>
                </div>
                <div class="stat-card" style="padding:12px;border:1px solid var(--border);border-radius:8px">
                    <div class="stat-label">Terlambat</div>
                    <div class="stat-value" style="font-size:18px;color:var(--warning)">{{ $payroll->late_count }}</div>
                </div>
                <div class="stat-card" style="padding:12px;border:1px solid var(--border);border-radius:8px">
                    <div class="stat-label">Izin</div>
                    <div class="stat-value" style="font-size:18px;color:var(--info)">{{ $payroll->izin_count }}</div>
                </div>
                <div class="stat-card" style="padding:12px;border:1px solid var(--border);border-radius:8px">
                    <div class="stat-label">Tidak Hadir</div>
                    <div class="stat-value" style="font-size:18px;color:var(--danger)">{{ $payroll->absent_count }}</div>
                </div>
            </div>
            <div class="alert alert-info no-print" style="margin:0">
                <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
                <div class="no-print">
                    Email slip & rekap dikirim otomatis tombol "Proses" di halaman Data Gaji.
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-calendar-grid"/></svg>
                Detail Kehadiran Bulan Ini
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Hari</th>
                            <th>Tanggal</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recap['details'] as $i => $d)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $d['day_name'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($d['date'])->translatedFormat('d M Y') }}</td>
                                <td>{{ $d['time_in'] ? substr($d['time_in'], 0, 5) : '-' }}</td>
                                <td>{{ $d['time_out'] ? substr($d['time_out'], 0, 5) : '-' }}</td>
                                <td>
                                    <span class="badge {{ \App\Models\Attendance::statusBadgeClassFor($d['status']) }}">
                                        <span class="badge-dot"></span> {{ $d['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layout>