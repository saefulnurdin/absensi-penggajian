<x-layout title="Pengaturan Shift">
    <div class="page-head">
        <div>
            <h1>Pengaturan Shift</h1>
            <div class="sub">Jam kerja per shift. Pegawai yang memilih shift akan memakai jam ini saat absensi; tanpa shift memakai jam default di Pengaturan</div>
        </div>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 1.4fr; gap:16px; align-items:start">
        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg>
                Tambah Shift
            </div>

            <form method="POST" action="{{ route('shifts.store') }}" style="display:flex;flex-direction:column;gap:12px">
                @csrf
                <div class="form-group">
                    <label for="name">Nama Shift</label>
                    <input type="text" id="name" name="name" placeholder="Contoh: Pagi, Siang, Malam" required maxlength="100">
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="start_time">Jam Mulai</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">Jam Selesai</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="grace_minutes">Toleransi Telat (menit)</label>
                    <input type="number" id="grace_minutes" name="grace_minutes" value="15" min="0" max="180">
                </div>
                <div class="form-help" style="margin-top:-4px">
                    Jam Selesai boleh <strong>lebih kecil</strong> dari Jam Mulai untuk shift lintas malam
                    (mis. Malam 23:00 - 07:00).
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg> Simpan Shift
                    </button>
                </div>
            </form>
        </div>

        <div class="card card-pad">
            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-clock"/></svg>
                Daftar Shift
            </div>

            @if ($shifts->count())
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Jam</th>
                            <th>Toleransi</th>
                            <th>Status</th>
                            <th style="width:170px">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($shifts as $shift)
                            <tr>
                                <td><strong>{{ $shift->name }}</strong></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('H:i') }}</td>
                                <td>{{ $shift->grace_minutes }} mnt</td>
                                <td>
                                    @if ($shift->is_active)
                                        <span class="badge badge-success">Aktif</span>
                                    @else
                                        <span class="badge badge-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <form method="POST" action="{{ route('shifts.toggle', $shift) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm @if($shift->is_active) btn-ghost @else btn-primary @endif">
                                                {{ $shift->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('shifts.destroy', $shift) }}"
                                              onsubmit="return confirm('Hapus shift {{ $shift->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info" style="margin:0">
                    Belum ada shift. Tambahkan shift (mis. Pagi 08:00-16:00) lalu pilih pada pegawai.
                </div>
            @endif
        </div>
    </div>
</x-layout>