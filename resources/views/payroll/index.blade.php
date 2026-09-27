<x-layout title="Data Gaji">
    <div class="page-head">
        <div>
            <h1>Data Gaji</h1>
            <div class="sub">Penggajian sederhana: gaji pokok - potongan tidak hadir</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('payroll.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group">
                <label>Periode</label>
                <input type="month" name="period" value="{{ $period }}">
            </div>
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Tampilkan
            </button>

            <span style="flex:1"></span>

            <form method="POST" action="{{ route('payroll.generate') }}" style="display:inline">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-refresh"/></svg> Hitung Ulang Payroll
                </button>
            </form>
        </form>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--primary-50);color:var(--primary-700)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-users"/></svg>
            </div>
            <div>
                <div class="stat-label">Pegawai Terhitung</div>
                <div class="stat-value">{{ $summary['employees'] }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--danger-bg);color:var(--danger)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
            </div>
            <div>
                <div class="stat-label">Potongan Tidak Hadir</div>
                <div class="stat-value">Rp{{ number_format($summary['total_deduction'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--warning-bg);color:var(--warning)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-calculator"/></svg>
            </div>
            <div>
                <div class="stat-label">Potongan Kasbon</div>
                <div class="stat-value">Rp{{ number_format($summary['total_kasbon'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon" style="background:var(--success-bg);color:var(--success)">
                <svg><use href="{{ asset('img/icons.svg') }}#i-wallet"/></svg>
            </div>
            <div>
                <div class="stat-label">Total Gaji Bersih</div>
                <div class="stat-value">Rp{{ number_format($summary['total_net'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('payroll.send-many') }}" id="payroll-form">
        @csrf
        <div class="card">
            <div class="card-pad" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                <div>
                    <div class="card-title" style="margin:0">
                        <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg>
                        Payroll {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y') }}
                    </div>
                    <span style="font-size:12px;color:var(--text-muted)">Centang lalu kirim slip gaji & rekap via email</span>
                </div>
                <div class="tbl-actions">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.querySelectorAll('input[name=\'payroll_ids[]\']').forEach(c=>c.checked=true)">
                        Pilih Semua
                    </button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <svg><use href="{{ asset('img/icons.svg') }}#i-send"/></svg> Kirim Email Terpilih
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:34px"></th>
                            <th>Pegawai</th>
                            <th>Hari Kerja</th>
                            <th>Hadir</th>
                            <th>Izin</th>
                            <th>Tidak Hadir</th>
                            <th>Gaji Pokok</th>
                            <th>Potongan</th>
                            <th>Kasbon</th>
                            <th>Gaji Bersih</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td>
                                    <input type="checkbox" name="payroll_ids[]" value="{{ $payroll->id }}" style="accent-color:var(--primary-600);width:16px;height:16px">
                                </td>
                                <td>
                                    <strong>{{ $payroll->employee->name }}</strong>
                                    <div style="color:var(--text-muted);font-size:12px">{{ $payroll->employee->employee_id }}</div>
                                </td>
                                <td>{{ $payroll->work_days }}</td>
                                <td>{{ $payroll->hadir_count }}</td>
                                <td>{{ $payroll->izin_count }}</td>
                                <td>
                                    @if ($payroll->absent_count > 0)
                                        <span class="badge badge-danger">{{ $payroll->absent_count }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td>Rp{{ number_format($payroll->base_salary, 0, ',', '.') }}</td>
                                <td style="color:var(--danger)">-Rp{{ number_format($payroll->deduction, 0, ',', '.') }}</td>
                                <td>
                                    @if ($payroll->kasbon_deduction > 0)
                                        <span style="color:var(--warning)">-Rp{{ number_format($payroll->kasbon_deduction, 0, ',', '.') }}</span>
                                    @else
                                        <span style="color:var(--text-muted)">-</span>
                                    @endif
                                </td>
                                <td><strong>Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}</strong></td>
                                <td>
                                    <span class="badge {{ $payroll->status === 'DIPROSES' ? 'badge-success' : 'badge-muted' }}">
                                        {{ $payroll->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <a href="{{ route('payroll.show', $payroll) }}" class="btn btn-outline btn-sm">
                                            <svg><use href="{{ asset('img/icons.svg') }}#i-eye"/></svg> Rincian
                                        </a>
                                        <a href="{{ route('payroll.slip', $payroll) }}" class="btn btn-outline btn-sm" target="_blank">
                                            <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg> Slip
                                        </a>
                                        <form action="{{ route('payroll.process', $payroll) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <svg><use href="{{ asset('img/icons.svg') }}#i-send"/></svg> Proses
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">
                                        <svg><use href="{{ asset('img/icons.svg') }}#i-wallet"/></svg>
                                        <div>
                                            Belum ada data payroll untuk periode ini.<br>
                                            Klik <strong>"Hitung Ulang Payroll"</strong> untuk membuatnya.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="card card-pad" style="margin-top:16px">
        <div class="card-title">
            <svg><use href="{{ asset('img/icons.svg') }}#i-calculator"/></svg>
            Rumus Perhitungan (Prototype)
        </div>
        <ul style="margin:0;padding-left:20px;color:var(--text-soft);line-height:2">
            <li><strong>Gaji Harian</strong> = Gaji Pokok &divide; Jumlah Hari Kerja Periode</li>
            <li><strong>Potongan Tidak Hadir</strong> = Jumlah Hari Tidak Hadir &times; Gaji Harian</li>
            <li><strong>Potongan Kasbon</strong> = total kasbon APPROVED dengan periode potong = periode ini (otomatis berstatus PAID saat dihitung)</li>
            <li><strong>Gaji Bersih</strong> = Gaji Pokok &minus; Potongan Tidak Hadir &minus; Potongan Kasbon</li>
            <li>Keterlambatan &amp; izin <strong>tidak</strong> memotong gaji pada prototype ini.</li>
        </ul>
    </div>
</x-layout>