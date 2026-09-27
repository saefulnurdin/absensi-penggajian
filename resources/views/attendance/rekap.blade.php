<x-layout title="Rekap Absensi">
    <div class="page-head">
        <div>
            <h1>Rekap Absensi</h1>
            <div class="sub">Rekap bulanan berdasarkan konfigurasi hari kerja</div>
        </div>
        <div class="page-actions">
            <form method="GET" action="{{ route('rekap.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group">
                    <label>Bulan</label>
                    <select name="month">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" @selected($month === $m)>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun</label>
                    <input type="number" name="year" value="{{ $year }}" min="2020" max="2100" style="width:90px">
                </div>
                <div class="form-group">
                    <label>Pegawai</label>
                    <select name="employee_id">
                        <option value="">Semua pegawai</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected($employeeId == $employee->id)>
                                {{ $employee->employee_id }} - {{ $employee->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-outline">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Tampilkan
                </button>
                <button type="button" class="btn btn-outline no-print" onclick="window.print()">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-print"/></svg> Cetak
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>Hari Kerja</th>
                        <th>Hadir</th>
                        <th>Terlambat</th>
                        <th>Izin</th>
                        <th>Tidak Hadir</th>
                        <th>Tdk Lengkap</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['employee']->name }}</strong>
                                <div style="color:var(--text-muted);font-size:12px">{{ $row['employee']->employee_id }} &bull; {{ $row['employee']->position }}</div>
                            </td>
                            <td>{{ $row['work_days'] }}</td>
                            <td>
                                <span class="badge badge-success">{{ $row['hadir'] }}</span>
                            </td>
                            <td>
                                <span class="badge badge-warning">{{ $row['terlambat'] }}</span>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $row['izin'] }}</span>
                            </td>
                            <td>
                                <span class="badge badge-danger">{{ $row['tidak_hadir'] }}</span>
                            </td>
                            <td>
                                <span class="badge badge-muted">{{ $row['incomplete'] }}</span>
                            </td>
                            <td>
                                <a href="{{ route('calendar.index', ['employee_id' => $row['employee']->id, 'month' => $month, 'year' => $year]) }}" class="btn btn-outline btn-sm">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-eye"/></svg> Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg>
                                    <div>Belum ada data. Pastikan ada pegawai aktif.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($rows->count() === 1)
        @php $row = $rows->first(); @endphp
        <div class="card" style="margin-top:16px">
            <div class="card-pad">
                <div class="card-title">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-calendar"/></svg>
                    Detail Per Hari - {{ $row['employee']->name }}
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Tanggal</th>
                                <th>Masuk</th>
                                <th>Pulang</th>
                                <th>Status</th>
                                <th>Sumber</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($row['details'] as $d)
                                <tr>
                                    <td>{{ $d['day_name'] }}</td>
                                    <td>{{ \Carbon\Carbon::parse($d['date'])->format('d M Y') }}</td>
                                    <td>{{ $d['time_in'] ? substr($d['time_in'], 0, 5) : '-' }}</td>
                                    <td>{{ $d['time_out'] ? substr($d['time_out'], 0, 5) : '-' }}</td>
                                    <td>
                                        <span class="badge {{ \App\Models\Attendance::statusBadgeClassFor($d['status']) }}">
                                            <span class="badge-dot"></span> {{ $d['status'] }}
                                            @if ($d['late_minutes']) ({{ $d['late_minutes'] }} mnt) @endif
                                        </span>
                                    </td>
                                    <td>{{ $d['source'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-layout>