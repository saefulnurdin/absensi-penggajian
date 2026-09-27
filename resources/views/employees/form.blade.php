<x-layout :title="$employee->exists ? 'Edit Pegawai' : 'Tambah Pegawai'">
    <div class="page-head">
        <div>
            <h1>{{ $employee->exists ? 'Edit Pegawai' : 'Tambah Pegawai' }}</h1>
            <div class="sub">Lengkapi data pegawai dan daftarkan UID kartu RFID</div>
        </div>
        <div class="page-actions">
            <a href="{{ route('employees.index') }}" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-chev-left"/></svg> Kembali
            </a>
        </div>
    </div>

    <div class="card card-pad">
        <form method="POST"
              action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
            @csrf
            @if ($employee->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="form-group">
                    <label for="employment_type">Jenis Pegawai</label>
                    <select id="employment_type" name="employment_type" onchange="toggleContract(this.value)">
                        <option value="">-- Pilih --</option>
                        @foreach (\App\Models\Employee::TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(old('employment_type', $employee->employment_type ?? 'KONTRAK') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-help">KARTAP = tetap, KONTRAK/MAGANG wajib mengisi rentang masa kerja.</div>
                </div>

                <div class="form-group">
                    <label for="shift_id">Shift Kerja</label>
                    <select id="shift_id" name="shift_id">
                        <option value="">Default (jam global)</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected(old('shift_id', $employee->shift_id) == $shift->id)>
                                {{ $shift->name }} ({{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('H:i') }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-help">Kosong = memakai jam masuk pulang global di Pengaturan.</div>
                </div>

                <div class="form-group contract-range" id="contract-start-group">
                    <label for="contract_start">Mulai Kontrak/Magang <span class="req">*</span></label>
                    <input type="date" id="contract_start" name="contract_start"
                           value="{{ old('contract_start', $employee->contract_start?->format('Y-m-d')) }}">
                </div>

                <div class="form-group contract-range" id="contract-end-group">
                    <label for="contract_end">Berakhir Kontrak/Magang <span class="req">*</span></label>
                    <input type="date" id="contract_end" name="contract_end"
                           value="{{ old('contract_end', $employee->contract_end?->format('Y-m-d')) }}">
                    <div class="form-help">Digunakan untuk validasi masa kerja, tidak mengubah perhitungan gaji.</div>
                </div>

                <div class="form-group">
                    <label for="employee_id">ID Pegawai <span class="req">*</span></label>
                    <input type="text" id="employee_id" name="employee_id"
                           value="{{ old('employee_id', $employee->employee_id) }}"
                           placeholder="PGW001" required>
                    <div class="form-help">Kode unik, contoh: PGW001</div>
                </div>

                <div class="form-group">
                    <label for="name">Nama Lengkap <span class="req">*</span></label>
                    <input type="text" id="name" name="name"
                           value="{{ old('name', $employee->name) }}"
                           placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="position">Jabatan</label>
                    <input type="text" id="position" name="position"
                           value="{{ old('position', $employee->position) }}"
                           placeholder="Staff">
                </div>

                <div class="form-group">
                    <label for="base_salary">Gaji Pokok (Rp) <span class="req">*</span></label>
                    <input type="number" id="base_salary" name="base_salary" min="0" step="0.01"
                           value="{{ old('base_salary', $employee->base_salary) }}" required>
                </div>

                <div class="form-group">
                    <label for="status">Status Pegawai</label>
                    <select id="status" name="status">
                        <option value="Aktif" @selected(old('status', $employee->status) === 'Aktif')>Aktif</option>
                        <option value="Nonaktif" @selected(old('status', $employee->status) === 'Nonaktif')>Nonaktif</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="hire_date">Tanggal Mulai Bekerja</label>
                    <input type="date" id="hire_date" name="hire_date"
                           value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $employee->email) }}"
                           placeholder="nama@example.com">
                    <div class="form-help">Untuk notifikasi email (slip gaji, keterlambatan, dll).</div>
                </div>

                <div class="form-group">
                    <label for="phone">No. Telepon</label>
                    <input type="text" id="phone" name="phone"
                           value="{{ old('phone', $employee->phone) }}"
                           placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group">
                    <label for="phone_wa">No. WhatsApp <span class="req">*</span></label>
                    <input type="text" id="phone_wa" name="phone_wa"
                           value="{{ old('phone_wa', $employee->phone_wa) }}"
                           placeholder="08xxxxxxxxxx">
                    <div class="form-help">Untuk pengiriman slip gaji &amp; notifikasi via WhatsApp. Format 08xxx / +628xxx.</div>
                </div>

                <div class="form-group">
                    <label for="rfid_uid">UID Kartu RFID</label>
                    <input type="text" id="rfid_uid" name="rfid_uid"
                           value="{{ old('rfid_uid', $employee->rfidCard?->uid ?? '') }}"
                           placeholder="A1B2C3D4" style="text-transform:uppercase">
                    <div class="form-help">
                        Kosongkan untuk tanpa kartu. UID unik, satu kartu hanya untuk satu pegawai aktif.
                        Baca UID dari perangkat ESP32 atau tulisan pada kartu.
                    </div>
                </div>

                <div class="form-group">
                    <label for="rfid_note">Catatan Kartu</label>
                    <input type="text" id="rfid_note" name="rfid_note"
                           value="{{ old('rfid_note', $employee->rfidCard?->note) }}"
                           placeholder="Kartu utama">
                </div>
            </div>

            <div class="form-actions" style="margin-top:18px">
                <button type="submit" class="btn btn-primary">
                    <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg>
                    {{ $employee->exists ? 'Simpan Perubahan' : 'Simpan Pegawai' }}
                </button>
                <a href="{{ route('employees.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function toggleContract(type) {
                const isContract = type === 'KONTRAK' || type === 'MAGANG';
                document.querySelectorAll('.contract-range').forEach(el => {
                    el.style.opacity = isContract ? '1' : '0.5';
                });
                document.querySelectorAll('.contract-range input').forEach(input => {
                    if (!isContract) { input.value = ''; }
                });
            }
            toggleContract(document.getElementById('employment_type').value || '');
        </script>
    @endpush
</x-layout>