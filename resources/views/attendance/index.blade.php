<x-layout title="Data Absensi">
    <div class="page-head">
        <div>
            <h1>Data Absensi</h1>
            <div class="sub">Lihat, filter, edit, dan kelola seluruh catatan kehadiran</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg> Absensi Manual
            </a>
            <a href="{{ route('rekap.index') }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-file-text"/></svg> Rekap
            </a>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('attendance.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:flex-end">
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="date" value="{{ request('date') }}">
            </div>
            <div class="form-group">
                <label>Pegawai</label>
                <select name="employee_id">
                    <option value="">Semua pegawai</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>
                            {{ $employee->employee_id }} - {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">Semua status</option>
                    @foreach (\App\Models\Attendance::ALL_STATUSES as $st)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Sumber</label>
                <select name="source">
                    <option value="">Semua</option>
                    @foreach (['RFID', 'KEYPAD', 'ADMIN'] as $src)
                        <option value="{{ $src }}" @selected(request('source') === $src)>{{ $src }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Filter
            </button>
            <a href="{{ route('attendance.index') }}" class="btn btn-ghost">Reset</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pegawai</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Status</th>
                        <th>Terlambat</th>
                        <th>Sumber</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attendance as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->attendance_date->translatedFormat('d M Y') }}</strong>
                                <div style="color:var(--text-muted);font-size:12px">{{ $row->attendance_date->translatedFormat('l') }}</div>
                            </td>
                            <td>
                                <strong>{{ $row->employee->name }}</strong>
                                <div style="color:var(--text-muted);font-size:12px">{{ $row->employee->employee_id }}</div>
                            </td>
                            <td>{{ $row->time_in ? substr($row->time_in, 0, 5) : '-' }}</td>
                            <td>{{ $row->time_out ? substr($row->time_out, 0, 5) : '-' }}</td>
                            <td>
                                <span class="badge {{ $row->statusBadgeClass() }}">
                                    <span class="badge-dot"></span> {{ $row->status }}
                                </span>
                            </td>
                            <td>
                                @if ($row->late_minutes)
                                    <span class="badge badge-warning">{{ $row->late_minutes }} mnt</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td><span class="badge badge-muted">{{ $row->source }}</span></td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="{{ route('attendance.edit', $row) }}" class="btn btn-outline btn-sm">
                                        <svg><use href="{{ asset('img/icons.svg') }}#i-edit"/></svg> Edit
                                    </a>
                                    <form action="{{ route('attendance.destroy', $row) }}" method="POST" data-confirm="Hapus absensi {{ $row->employee->name }} tanggal {{ $row->attendance_date->format('d-m-Y') }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <svg><use href="{{ asset('img/icons.svg') }}#i-trash"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg>
                                    <div>Tidak ada data absensi untuk filter tersebut.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">{{ $attendance->links() }}</div>
</x-layout>