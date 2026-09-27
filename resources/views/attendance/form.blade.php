<x-layout :title="$attendance->exists ? 'Edit Absensi' : 'Absensi Manual'">
    <div class="page-head">
        <div>
            <h1>{{ $attendance->exists ? 'Edit Absensi' : 'Tambahkan Absensi Manual' }}</h1>
            <div class="sub">Sumber akan tercatat sebagai ADMIN</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('attendance.index') }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-chev-left"/></svg> Kembali
            </a>
        </div>
    </div>

    <div class="card card-pad" style="max-width:720px">
        <form method="POST"
              action="{{ $attendance->exists ? route('attendance.update', $attendance) : route('attendance.store') }}">
            @csrf
            @if ($attendance->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="form-group">
                    <label for="employee_id">Pegawai <span class="req">*</span></label>
                    <select id="employee_id" name="employee_id" required>
                        <option value="">-- Pilih pegawai --</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}"
                                @selected(old('employee_id', $attendance->employee_id) == $employee->id)>
                                {{ $employee->employee_id }} - {{ $employee->name }} ({{ $employee->position }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="attendance_date">Tanggal <span class="req">*</span></label>
                    <input type="date" id="attendance_date" name="attendance_date"
                           value="{{ old('attendance_date', $attendance->attendance_date?->format('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label for="time_in">Jam Masuk</label>
                    <input type="time" id="time_in" name="time_in"
                           value="{{ old('time_in', $attendance->time_in ? substr($attendance->time_in, 0, 5) : null) }}">
                </div>

                <div class="form-group">
                    <label for="time_out">Jam Pulang</label>
                    <input type="time" id="time_out" name="time_out"
                           value="{{ old('time_out', $attendance->time_out ? substr($attendance->time_out, 0, 5) : null) }}">
                </div>

                <div class="form-group">
                    <label for="status">Status <span class="req">*</span></label>
                    <select id="status" name="status" required>
                        @foreach (\App\Models\Attendance::ALL_STATUSES as $st)
                            <option value="{{ $st }}" @selected(old('status', $attendance->status) === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="late_minutes">Keterlambatan (menit)</label>
                    <input type="number" id="late_minutes" name="late_minutes" min="0"
                           value="{{ old('late_minutes', $attendance->late_minutes) }}">
                    <div class="form-help">Auto dihitung untuk data dari ESP32. Manual dapat diisi sesuai kebutuhan.</div>
                </div>

                <div class="form-group full">
                    <label for="note">Keterangan</label>
                    <textarea id="note" name="note" placeholder="Opsional">{{ old('note', $attendance->note) }}</textarea>
                </div>

                @if ($attendance->exists)
                    <div class="form-group full">
                        <label for="edit_reason">Alasan Perubahan <span class="req">*</span></label>
                        <input type="text" id="edit_reason" name="edit_reason"
                               value="{{ old('edit_reason') }}" placeholder="Contoh: koreksi data, lupa tap, rekam manual"
                               required>
                        <div class="form-help">Tercatat untuk audit perubahan data.</div>
                    </div>
                @endif
            </div>

            <div class="form-actions" style="margin-top:18px">
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg>
                    {{ $attendance->exists ? 'Simpan Perubahan' : 'Simpan Absensi' }}
                </button>
                <a href="{{ route('attendance.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
</x-layout>