<x-layout title="Kasbon">
    <div class="page-head">
        <div>
            <h1>Kasbon</h1>
            <div class="sub">Pinjaman karyawan yang dipotong dari gaji pada periode yang ditentukan</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="card card-pad" style="margin-bottom:16px">
        <div class="card-title">
            <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg>
            Tambah Kasbon
        </div>
        <form method="POST" action="{{ route('kasbon.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;align-items:end">
                <div class="form-group" style="margin:0">
                    <label>Pegawai</label>
                    <select name="employee_id" required>
                        <option value="">Pilih pegawai</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->employee_id }} — {{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Jumlah (Rp)</label>
                    <input type="number" name="amount" min="1" step="1" required placeholder="500000">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Potong dari Gaji</label>
                    <input type="month" name="deduct_period" value="{{ now()->format('Y-m') }}" required>
                    <div class="form-help">Periode gaji yang menanggung kasbon.</div>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Keterangan (opsional)</label>
                    <input type="text" name="note" maxlength="255" placeholder="mis. kebutuhan keluarga">
                </div>
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg> Tambah Kasbon
                </button>
            </div>
        </form>
        <p style="margin:10px 0 0;font-size:12px;color:var(--text-muted)">
            Alur: <strong>PENDING</strong> → <strong>Setujui</strong> → saat payroll periode terpilih dihitung ulang,
            kasbon otomatis dipotong dari gaji dan berstatus <strong>PAID</strong>.
        </p>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('kasbon.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">Semua</option>
                    @foreach (['PENDING', 'APPROVED', 'PAID', 'CANCELLED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Pegawai</label>
                <select name="employee_id">
                    <option value="">Semua</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>
                            {{ $employee->employee_id }} — {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Filter
            </button>
            <a href="{{ route('kasbon.index') }}" class="btn btn-ghost">Reset</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pegawai</th>
                        <th>Jumlah</th>
                        <th>DiPotong Gaji</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kasbons as $kasbon)
                        <tr>
                            <td>{{ $kasbon->created_at->translatedFormat('d M Y') }}</td>
                            <td>
                                <strong>{{ $kasbon->employee->name }}</strong>
                                <div style="color:var(--text-muted);font-size:12px">{{ $kasbon->employee->employee_id }}</div>
                            </td>
                            <td><strong>Rp{{ number_format($kasbon->amount, 0, ',', '.') }}</strong></td>
                            <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $kasbon->deduct_period)->translatedFormat('F Y') }}</td>
                            <td>{{ $kasbon->note ?? '-' }}</td>
                            <td>
                                @php
                                    $class = match ($kasbon->status) {
                                        'APPROVED' => 'badge-warning',
                                        'PAID' => 'badge-success',
                                        'CANCELLED' => 'badge-muted',
                                        default => 'badge-muted',
                                    };
                                @endphp
                                <span class="badge {{ $class }}">
                                    @if ($kasbon->status === 'PAID')
                                        PAID {{ $kasbon->paid_in_period }}
                                    @else
                                        {{ $kasbon->status }}
                                    @endif
                                </span>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    @if ($kasbon->status === 'PENDING')
                                        <form action="{{ route('kasbon.approve', $kasbon) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg> Setujui
                                            </button>
                                        </form>
                                    @endif
                                    @if (in_array($kasbon->status, ['PENDING', 'APPROVED']))
                                        <form action="{{ route('kasbon.cancel', $kasbon) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm">
                                                <svg><use href="{{ asset('img/icons.svg') }}#i-x"/></svg> Batal
                                            </button>
                                        </form>
                                    @endif
                                    @if ($kasbon->status !== 'PAID')
                                        <form action="{{ route('kasbon.destroy', $kasbon) }}" method="POST"
                                              onsubmit="return confirm('Hapus kasbon ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm">
                                                <svg><use href="{{ asset('img/icons.svg') }}#i-trash"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-calculator"/></svg>
                                    <div>Belum ada data kasbon.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="pagination">{{ $kasbons->links() }}</div>
</x-layout>