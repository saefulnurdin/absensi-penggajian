<x-layout title="Pengaturan Hari Kerja">
    <div class="page-head">
        <div>
            <h1>Pengaturan Hari Kerja</h1>
            <div class="sub">Tentukan hari apa saja yang dianggap hari kerja untuk perhitungan absensi & gaji</div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:720px">
        <form method="POST" action="{{ route('work-days.update') }}">
            @csrf
            @method('PUT')

            <div class="card-title">
                <svg><use href="{{ asset('img/icons.svg') }}#i-calendar-grid"/></svg>
                Hari Kerja Mingguan
            </div>

            <div class="grid grid-2">
                @foreach (range(0, 6) as $index)
                    @php
                        $day = $workDays->get($index);
                        $active = $day ? (bool) $day->is_work_day : ($index >= 1 && $index <= 5);
                        $nama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$index];
                    @endphp
                    <label class="check-row @if($active) active @endif">
                        <input type="checkbox" name="active[]" value="{{ $index }}"
                               @checked($active) onchange="this.closest('.check-row').classList.toggle('active', this.checked)">
                        {{ $nama }}
                    </label>
                @endforeach
            </div>

            <div class="alert alert-info" style="margin-top:16px;margin-bottom:0">
                <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
                <div>
                    <strong>Catatan:</strong> Hari yang tidak dicentang dianggap libur dan <b>tidak</b> dihitung sebagai
                    "tidak hadir" dalam rekap maupun potongan gaji. Sabtu/Minggu tidak otomatis libur — semuanya bergantung
                    pada pengaturan ini.
                </div>
            </div>

            <div class="form-actions" style="margin-top:16px">
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg> Simpan Hari Kerja
                </button>
            </div>
        </form>
    </div>

    <div class="card card-pad" style="max-width:720px">
        <form method="POST" action="{{ route('work-days.overrides.store') }}" class="grid grid-2" style="gap:12px">
            @csrf

            <div class="card-title" style="grid-column:1/-1">
                <svg><use href="{{ asset('img/icons.svg') }}#i-plus"/></svg>
                Tanggal Khusus (Override)
            </div>

            <p style="grid-column:1/-1;margin:0" class="sub">
                Atur tanggal tertentu yang berbeda dari pola mingguan (hari libur nasional, kerja tambahan, dsb.
                Waktu kosong berarti memakai jam masuk/keluar global).
            </p>

            <div>
                <label for="ov_date">Tanggal</label>
                <input type="date" id="ov_date" name="work_date" required>
            </div>

            <div>
                <label for="ov_label">Keterangan (opsional)</label>
                <input type="text" id="ov_label" name="label" maxlength="100"
                       placeholder="Contoh: Libur Nasional / Kerja Lembur">
            </div>

            <div>
                <label for="ov_start">Jam Masuk (opsional)</label>
                <input type="time" id="ov_start" name="start_time">
            </div>

            <div>
                <label for="ov_end">Jam Pulang (opsional)</label>
                <input type="time" id="ov_end" name="end_time">
            </div>

            <div style="grid-column:1/-1;display:flex;align-items:center;gap:10px">
                <label class="check-row active" style="margin:0">
                    <input type="checkbox" name="is_work_day" value="1" checked
                           onchange="this.closest('.check-row').classList.toggle('active', this.checked)">
                    Aktif sebagai hari kerja
                </label>
                <span style="flex:1"></span>
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg> Simpan Tanggal
                </button>
            </div>
        </form>

        @if ($overrides->count())
            <div class="table-wrap" style="margin-top:20px">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Jam</th>
                        <th>Keterangan</th>
                        <th style="width:80px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($overrides as $override)
                        <tr>
                            <td>{{ $override->work_date->format('d M Y') }}</td>
                            <td>
                                @if ($override->is_work_day)
                                    <span class="badge badge-success">Hari Kerja</span>
                                @else
                                    <span class="badge badge-danger">Libur</span>
                                @endif
                            </td>
                            <td>
                                {{ $override->start_time ? \Illuminate\Support\Carbon::parse($override->start_time)->format('H:i').' - '.($override->end_time ? \Illuminate\Support\Carbon::parse($override->end_time)->format('H:i') : '?') : 'Default (global)' }}
                            </td>
                            <td>{{ $override->label ?: '-' }}</td>
                            <td>
                                <form method="POST" action="{{ route('work-days.overrides.destroy', $override) }}"
                                      onsubmit="return confirm('Hapus tanggal khusus ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $overrides->links() }}</div>
        @else
            <div class="alert alert-info" style="margin-top:20px;margin-bottom:0">
                Belum ada tanggal khusus. Semua hari mengikuti pola mingguan di atas.
            </div>
        @endif
    </div>
</x-layout>