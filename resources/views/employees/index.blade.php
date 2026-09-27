<x-layout title="Data Pegawai">
    <div class="page-head">
        <div>
            <h1>Data Pegawai</h1>
            <div class="sub">Kelola data pegawai dan registrasi kartu RFID</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('employees.create') }}" class="btn btn-primary">
                <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg> Tambah Pegawai
            </a>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('employees.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:flex-end">
            <div class="form-group">
                <label>Nama / ID</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari pegawai...">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">Semua</option>
                    <option value="Aktif" @selected(request('status') === 'Aktif')>Aktif</option>
                    <option value="Nonaktif" @selected(request('status') === 'Nonaktif')>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-search"/></svg> Cari
            </button>
            <a href="{{ route('employees.index') }}" class="btn btn-ghost">Reset</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID Pegawai</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Jenis</th>
                    <th>Shift</th>
                    <th>RFID</th>
                    <th>Gaji Pokok</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td><strong>{{ $employee->employee_id }}</strong></td>
                            <td>
                                <strong>{{ $employee->name }}</strong>
                                @if ($employee->email)
                                    <div style="color:var(--text-muted);font-size:12px">{{ $employee->email }}</div>
                                @endif
                            </td>
<td>{{ $employee->position }}</td>
                        <td>
                            @if ($employee->employment_type)
                                @php
                                    $typeBadge = match ($employee->employment_type) {
                                        'KARTAP' => 'badge-primary',
                                        'KONTRAK' => 'badge-warning',
                                        default => 'badge-info',
                                    };
                                @endphp
                                <span class="badge {{ $typeBadge }}">{{ $employee->employment_type }}</span>
                            @else
                                <span class="badge badge-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if ($employee->shift)
                                <span class="badge badge-success">{{ $employee->shift->name }}</span>
                                <div style="color:var(--text-muted);font-size:12px">
                                    {{ \Illuminate\Support\Carbon::parse($employee->shift->start_time)->format('H:i') }} -
                                    {{ \Illuminate\Support\Carbon::parse($employee->shift->end_time)->format('H:i') }}
                                </div>
                            @else
                                <span class="badge badge-muted">Default</span>
                            @endif
                        </td>
                        <td>
                                @if ($employee->rfidCard)
                                    <span class="badge badge-primary"><span class="badge-dot"></span> {{ $employee->rfidCard->uid }}</span>
                                @else
                                    <span class="badge badge-muted">Belum terdaftar</span>
                                @endif
                            </td>
                            <td>Rp{{ number_format($employee->base_salary, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $employee->status === 'Aktif' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $employee->status }}
                                </span>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-outline btn-sm">
                                        <svg><use href="{{ asset('img/icons.svg') }}#i-edit"/></svg> Edit
                                    </a>
                                    <form action="{{ route('employees.destroy', $employee) }}" method="POST" data-confirm="Yakin menghapus pegawai {{ $employee->name }}? Riwayat absensinya ikut terhapus.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <svg><use href="{{ asset('img/icons.svg') }}#i-trash"/></svg> Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-users"/></svg>
                                    <div>Belum ada pegawai.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">{{ $employees->links() }}</div>
</x-layout>