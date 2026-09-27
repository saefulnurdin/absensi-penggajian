<x-layout title="Kelola RFID">
    <div class="page-head">
        <div>
            <h1>Kelola RFID Card</h1>
            <div class="sub">Daftarkan kartu baru, pindahkan kartu, atau nonaktifkan kartu</div>
        </div>
    </div>

    @if ($pending->isNotEmpty())
        <div class="card card-pad" style="margin-bottom:16px">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-rfid"/></svg>
                Kartu Baru Terdeteksi dari Perangkat
                <span class="badge badge-warning" style="margin-left:8px">{{ $pending->total() }} kartu</span>
            </div>
            <div class="form-help" style="margin-bottom:12px">
                UID ini muncul otomatis saat kartu yang belum terdaftar ditap di perangkat.
                Pilih pegawai lalu klik <strong>Daftarkan</strong> untuk mengaktifkannya.
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>UID Terdeteksi</th>
                            <th>Ditap</th>
                            <th>Terakhir Tap</th>
                            <th>Daftarkan Ke</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $row)
                            <tr>
                                <td><code style="font-size:13px">{{ $row->uid }}</code></td>
                                <td><span class="badge badge-neutral">{{ $row->hits }}×</span></td>
                                <td>{{ $row->last_seen_at?->format('d-m-Y H:i:s') }}</td>
                                <td>
                                    @if ($employees->isEmpty())
                                        <span class="form-help">Semua pegawai sudah punya kartu.</span>
                                    @else
                                        <form action="{{ route('rfid-cards.store') }}" method="POST"
                                              onsubmit="return confirm('Daftarkan UID {{ $row->uid }} untuk pegawai terpilih?')">
                                            @csrf
                                            <input type="hidden" name="uid" value="{{ $row->uid }}">
                                            <select name="employee_id" required onchange="this.form.submit()">
                                                <option value="">-- Pilih pegawai --</option>
                                                @foreach ($employees as $employee)
                                                    <option value="{{ $employee->id }}">{{ $employee->employee_id }} - {{ $employee->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $pending->links() }}</div>
        </div>
    @endif

    <div class="grid grid-2">
        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg>
                Daftarkan Kartu RFID Baru
            </div>
            <form method="POST" action="{{ route('rfid-cards.store') }}">
                @csrf
                <div class="form-group" style="margin-bottom:12px">
                    <label>Pegawai (tanpa kartu aktif)</label>
                    <select name="employee_id" required>
                        <option value="">-- Pilih pegawai --</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->employee_id }} - {{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>UID Kartu <span class="req">*</span></label>
                    <input type="text" name="uid" placeholder="A1B2C3D4" style="text-transform:uppercase" required>
                    <div class="form-help">Unik. Kartu yang sama tidak dapat dipakai dua pegawai.</div>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>Catatan</label>
                    <input type="text" name="note" placeholder="Kartu pengganti">
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-rfid"/></svg> Daftarkan
                </button>
            </form>
        </div>

        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-rfid"/></svg>
                Daftar Kartu
            </div>

            <div class="filter-bar" style="margin-bottom:12px;margin-top:0">
                <form method="GET" action="{{ route('rfid-cards.index') }}" style="display:flex;gap:10px;width:100%">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari UID / nama pegawai...">
                    <button type="submit" class="btn btn-outline">
                        <svg><use href="{{ asset('img/icons.svg') }}#i-search"/></svg> Cari
                    </button>
                    <a href="{{ route('rfid-cards.index') }}" class="btn btn-ghost">Reset</a>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>UID</th>
                            <th>Pegawai</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cards as $card)
                            <tr>
                                <td><code style="font-size:13px">{{ $card->uid }}</code></td>
                                <td>
                                    <strong>{{ $card->employee->name }}</strong>
                                    <div style="color:var(--text-muted);font-size:12px">{{ $card->employee->employee_id }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $card->is_active ? 'badge-success' : 'badge-muted' }}">
                                        {{ $card->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <form action="{{ route('rfid-cards.toggle', $card) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm">
                                                {{ $card->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('rfid-cards.destroy', $card) }}" method="POST" data-confirm="Hapus kartu {{ $card->uid }}?">
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
                                <td colspan="4">
                                    <div class="empty-state">
                                        <svg><use href="{{ asset('img/icons.svg') }}#i-rfid"/></svg>
                                        <div>Belum ada kartu RFID.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $cards->links() }}</div>
        </div>
    </div>
</x-layout>