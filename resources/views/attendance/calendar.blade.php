<x-layout title="Kalender Absensi">
    <div class="page-head">
        <div>
            <h1>Kalender Absensi</h1>
            <div class="sub">Status kehadiran harian satu pegawai dalam satu bulan</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('calendar.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;width:100%">
            <div class="form-group" style="flex:1;min-width:220px">
                <label>Pegawai</label>
                <select name="employee_id">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($employee->id === $employeeId)>
                            {{ $employee->employee_id }} - {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
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
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Tampilkan
            </button>
            <a href="{{ route('rekap.index', ['employee_id' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg> Rekap
            </a>
        </form>
    </div>

    <div class="card">
        <div class="card-pad" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <div>
                <h2 style="margin:0">{{ $employee->name }}</h2>
                <div class="sub" style="color:var(--text-muted);font-size:13px">
                    {{ $employee->employee_id }} &bull; {{ $employee->position }} &bull;
                    {{ \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y') }}
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <span class="badge badge-success">Hadir {{ $recap['hadir'] }}</span>
                <span class="badge badge-warning">Terlambat {{ $recap['terlambat'] }}</span>
                <span class="badge badge-info">Izin {{ $recap['izin'] }}</span>
                <span class="badge badge-danger">Tidak Hadir {{ $recap['tidakHadir'] }}</span>
                <span class="badge badge-muted">Tdk Lengkap {{ $recap['incomplete'] }}</span>
            </div>
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
                        <th>Terlambat</th>
                        <th>Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recap['details'] as $i => $d)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $d['day_name'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($d['date'])->translatedFormat('d F Y') }}</td>
                            <td>{{ $d['time_in'] ? substr($d['time_in'], 0, 5) : '-' }}</td>
                            <td>{{ $d['time_out'] ? substr($d['time_out'], 0, 5) : '-' }}</td>
                            <td>
                                <span class="badge {{ \App\Models\Attendance::statusBadgeClassFor($d['status']) }}">
                                    <span class="badge-dot"></span> {{ $d['status'] }}
                                </span>
                            </td>
                            <td>{{ $d['late_minutes'] ? $d['late_minutes'].' mnt' : '-' }}</td>
                            <td>{{ $d['source'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">Tidak ada hari kerja pada periode ini.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>