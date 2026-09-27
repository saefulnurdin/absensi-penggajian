# Alur Sistem & Panduan Pengujian

## 1. Flow Diagram Tahapan Demo

```
┌───────────────────────────── 1. TAP KARTU RFID ─────────────────────────────┐
│  Cartu didekatkan ke MFRC522 (ESP32)                                        │
│  ESP32 baca UID → LCD: "Menunggu…"                                          │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   ▼
   ┌────────── ONLINE? ──────────┐
   │ YES                        NO
   ▼                            ▼
  Kirim ke server          Simpan ke Buffer OFFLINE (LittleFS)
  via HTTPS                + timestamp (NTP/RTC)
        │                         │  (Saat WiFi/server kembali)
        │                         ▼
        │              Flush buffer → kirim ulang otomatis
        ▼
┌──────────────────────────── 2. SERVER LARAVEL ──────────────────────────────┐
│  POST /api/attendance  {uid, mode:auto} + X-API-KEY                         │
│  Validasi: kartu aktif · pegawai aktif · hari kerja                         │
│  Sudah ada time_in?  → YA: PULANG      → TIDAK: MASUK                       │
│  Simpan Attendance + kirim EMAIL notifikasi (hadir / terlambat / pulang)    │
│  Balas JSON { success, status, message, beep }                              │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   ▼
                     ESP32 tampilkan pesan di LCD + buzzer
                                   ▼
┌──────────────────── 3. REKAP, PAYROLL, NOTIFIKASI ──────────────────────────┐
│  Admin login web → Rekap/Kalender: lihat status harian                     │
│  Data Gaji → Hitung Ulang Payroll → baris tiap pegawai                     │
│      (hari kerja · hadir · izin · tidak hadir · potongan · kasbon · bersih)│
│  Kasbon menu → Tambah → Setujui → potongan muncul saat hitung ulang        │
│  Proses / Kirim Email Terpilih → status DIPROSES                           │
│  → kirim SLIP via EMAIL (Gmail) + WHATSAPP (WAHA)  → Log Email & Log WA    │
│  Scheduler wa:recap (tiap 30 mnt) → rekap harian ke WA Admin               │
└────────────────────────────────────────────────────────────────────────────┘
```

## 2. Arsitektur Jaringan (untuk sidang)

```
[ESP32] ──WiFi (internet)──▶ https://app.absensi-penggajian.my.id
                                     │ Cloudflare Tunnel
                                     ▼
                             Laptop (php artisan serve + queue + schedule)
                                     │
                                     ├── MySQL (data)
                                     ├── Gmail SMTP (email)
                                     └── Docker WAHA → WhatsApp (gateway 6285814885811)

CATATAN:
- ESP32 dan server TIDAK harus satu jaringan WiFi.
- ESP32 cukup terhubung WiFi ber-internet; komunikasi lewat domain publik.
- Jika demo offline (tanpa tunnel), baru server & ESP harus satu jaringan
  (server: php artisan serve --host 0.0.0.0, ESP menuju http://IP-laptop:8000).
```

## 3. Prasyarat Sebelum Sidang

1. `php artisan migrate` — pastikan DB ter-update.
2. Jalankan pendamping (3 terminal):
    - `php artisan serve` (atau tunnel Cloudflare → pastikan `my.id` terbuka)
    - `php artisan queue:work`
    - `php artisan schedule:work`
3. Container WAHA hidup (`docker ps`), sesi `default` = WORKING
   (dashboard `http://localhost:3000`, QR sudah discan).
4. `php artisan test` → 104/104 hijau (bukti regresi).
5. Firmware ESP32 terbaru sudah diupload, hotspot HP berinternet.

## 4. Langkah Pengujian (Urut dari Awal)

### Tahap A — Persiapan Data Master (web)

1. Login `admin@absensi.local` / `admin1234`.
2. Pegawai: buat 2–3 pegawai (No. WhatsApp diisi, gaji pokok, shift, jabatan).
3. RFID Cards: input UID kartu → assign ke pegawai.
4. Hari Kerja: pastikan Senin–Jumat = kerja.
5. Shift: atur jam masuk/kontrak toleransi.
6. Pengaturan: No. WhatsApp Admin + Jam Rekap WA.

### Tahap A-Kedua — Data Dummy Otomatis (via `php artisan tinker`)

Agar tabel rekap & payroll langsung berisi data (tidak kosong di demo), paste
script di bawah ke tinker setelah master data dibuat:

```php
$emps = App\Models\Employee::where('status', 'Aktif')->get();
$svc  = app(App\Services\AttendanceService::class);
[$y, $m] = explode('-', now()->format('Y-m'));
$work = $svc->workDates((int) $y, (int) $m);
foreach ($emps as $emp) {
    foreach ($work as $i => $date) {
        if ($i % 10 == 8) {                              // tiap 10 hari: 1 izin
            App\Models\Attendance::updateOrCreate(
                ['employee_id' => $emp->id, 'attendance_date' => $date],
                ['status' => App\Models\Attendance::IZIN, 'source' => 'RFID']
            );
            continue;
        }
        if ($i % 10 == 9) { continue; }                  // 1 hari tidak hadir
        $late = $i % 7 == 3;                             // tiap 7 hari: terlambat
        $min  = str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT);
        App\Models\Attendance::updateOrCreate(
            ['employee_id' => $emp->id, 'attendance_date' => $date],
            [
                'time_in'       => $late ? "08:$min:00" : "08:00:00",
                'time_out'      => '17:00:00',
                'status'        => $late ? App\Models\Attendance::TERLAMBAT : App\Models\Attendance::HADIR,
                'late_minutes'  => $late ? 15 : null,
                'source'        => 'RFID',
            ]
        );
    }
}
echo 'OK, total absensi: '.App\Models\Attendance::count();
```

Catatan:

- Script hanya mengisi hari kerja bulan berjalan, deterministik (pola tetap),
  aman dijalankan ulang (updateOrCreate tidak duplikat).
- Setelah ini langsung uji **Tahap C** (rekap/kalender) & **Tahap D** (payroll).
- Dummy kasbon cukup dibuat via menu (Tahap D.3).

### Tahap B — Absensi (API tanpa ESP32 dulu)

Agar demo jalan walau ESP32 bermasalah, uji via Postman/curl:

```
POST https://app.absensi-penggajian.my.id/api/attendance
Header: X-API-KEY: penggajian-rfid-demo-2026  ; Content-Type: application/json
Body:   { "uid": "<UID kartu>", "mode": "auto" }
```

1. Tap pagi → respon `success:true, status:HADIR/TERLAMBAT, beep:1/3`.
2. Tap ulang → `status:PULANG`.
3. `mode:"izin"` → status IZIN.
4. Cek menu **Absensi** terisi & **Log Email** ada notifikasinya.

### Tahap C — Rekap & Kalender

1. Buka Rekap bulan berjalan → status per pegawai benar
   (hadir / terlambat / izin / tidak hadir / tidak lengkap).
2. Buka Kalender → tanda warna per hari.

### Tahap D — Payroll & Kasbon

1. **Data Gaji** → pilih periode → **Hitung Ulang Payroll**.
2. Verifikasi per pegawai: hari kerja, gaji harian, potongan absen, kotor → bersih.
3. **Kasbon** → Tambah (PENDING) → **Setujui** → **Hitung Ulang** → potongan kasbon
   muncul → status kasbon = PAID.
4. Buka **Rincian** & **Slip** → komponen lengkap (pokok, potongan absen, kasbon, bersih).

### Tahap E — Pengiriman Notifikasi (inti demo)

1. **Proses** satu payroll → status DIPROSES.
2. Buka **Log Email** dan **Log WhatsApp** → baris SENT baru.
3. Buka HP → slip gaji masuk (email + WhatsApp, format rapi).
4. **Rekap WA harian**: jalankan paksa `php artisan wa:recap --date=<hari ini>`
   → HP admin menerima rekap; atau set `wa_recap_time` ≈ jam demo lalu tunggu scheduler.
5. `queue:work` menunjukkan job diproses (log di terminal).

### Tahap F — Ujung-ke-ujung ESP32 (opsional, terakhir)

1. Hubungkan ESP32 ke hotspot HP (berinternet).
2. Tap kartu → LCD nama/jam, buzzer berhenti beep.
3. Data masuk web (Absensi) → notifikasi email terkirim → tampil di Rekap.

### Tahap F2 — Uji Buffer Offline → Sinkron Otomatis (bagian penting sidang)

Tujuan: membuktikan data tetap tercatat saat jaringan mati dan **masuk sendiri
saat internet kembali**.

**Skenario 1 — offline karena server/tunnel dimatikan (server tidak reachable):**

1. Putus/kill `queue` tidak perlu; cukup matikan tunnel atau `php artisan serve`,
   atau blokir koneksi ESP dengan mematikan data hp.
2. Tap kartu 3× (pagi-masuk-pulang-baru) → LCD tampil **"WIFI OFFLINE — Antre: N"**,
   buzzer tetap "sukses" (data TIDAK hilang).
3. Nyalakan kembali server/tunnel/hotspot.
4. Dalam ≤ beberapa detik ESP mengirim buffer → LCD **"KIRIM BUFFER — Sisa: 0"**.
5. Buka menu **Absensi** di web → ke-3 tap muncul **dengan timestamp asli saat tap**
   (bukan jam saat sinkron). Email notifikasi ikut terkirim.

**Skenario 2 — offline karena WiFi hotspot mati (belum connect ke jaringan):**

1. Matikan hotspot → tap kartu → tetap "OFFLINE — Antre: N".
2. Nyalakan hotspot → ESP otomatis menyambung kembali (tanpa reset) → flush buffer.
3. Verifikasi di **Absensi** & **email_logs**.

**Bukti yang ditunjukkan ke penguji:**

- Monitor Serial: `[BUF] antre=3 ... sisa=0` (urutan kirim sesuai antrean).
- LCD saat offline & saat sinkron (foto).
- Tabel di web berisi record bertimestamp lama (offline) → jadi bukti memakai
  waktu dari buffer, bukan waktu sinkron.

### Tahap G — Regresi

- `php artisan test` → 104/104 hijau (bisa ditampilkan sebagai bukti otomatisasi).

## 5. Catatan Hasil Uji Ujung-ke-ujung (2026-09-26)

Rekaman uji nyata: dummy data → payroll + kasbon → notifikasi WhatsApp **terkirim**.

### 5.1 Kondisi lingkungan saat uji

- Docker WAHA aktif: container `waha`, versi **2026.8.2** (CORE, engine WEBJS),
  sesi `default` = **WORKING** (tertaut `6285814885811@c.us`).
- `QUEUE_CONNECTION=database` — job diproses via `php artisan queue:work`.
- Server: MySQL (`penggajian`) + web Laravel (tunnel `app.absensi-penggajian.my.id`).
- Penerima notifikasi: `085691406905` → international `6285691406905` (WA admin).

### 5.2 Data dummy yang dipakai

- 7 pegawai Aktif (PGW001–PGW007), September 2026 = **22 hari kerja**.
- Absensi dummy memakai pola deterministik (hadir/terlambat/izin/tidak hadir)
  → total **140 baris** (script di Tahap A-Kedua).
- Kasbon demo: PGW001 Rp750.000 potong dari gaji **2026-09**, status APPROVED.

### 5.3 Hasil payroll September 2026 (setelah kasbon)

| Pegawai   | Nama        | Hari Kerja | Hadir | Terlambat | Izin | Tidak Hadir | Gaji Pokok | Potongan | Kasbon   | Gaji Bersih |
|-----------|-------------|-----------:|------:|----------:|-----:|------------:|-----------:|---------:|---------:|------------:|
| PGW001 | John Doe     | 22 | 18 | 3 | 2 | 2 | 5.000.000 | 454.546 | **750.000** | **3.795.454** |
| PGW002 | Jane Doe     | 22 | 18 | 3 | 2 | 2 | 4.500.000 | 409.090 | 0 | 4.090.910 |
| PGW003 | Budi Santoso | 22 | 18 | 3 | 2 | 2 | 7.500.000 | 681.818 | 0 | 6.818.182 |
| PGW004 | Andi Wijaya  | 22 | 18 | 3 | 2 | 2 | 4.800.000 | 436.364 | 0 | 4.363.636 |
| PGW005 | Siti Aminah  | 22 | 18 | 3 | 2 | 2 | 4.700.000 | 427.272 | 0 | 4.272.728 |
| PGW006 | Robbert      | 22 | 18 | 3 | 2 | 2 | 4.000.000 | 363.636 | 0 | 3.636.364 |
| PGW007 | Sine Nomine  | 22 | 18 | 3 | 2 | 2 | 5.000.000 | 454.546 | 0 | 4.545.454 |

- Kasbon PGW001 otomatis menjadi **PAID** (paid_in_period `2026-09`),
  gaji bersih turun dari 4.545.454 → **3.795.454** (tanpa potongan ganda saat regenerate).

### 5.4 Notifikasi WhatsApp yang terkirim (log nyata)

| ID | Jenis | Penerima | Status | Waktu |
|----|-------|----------|--------|-------|
| 3 | `slip-gaji` | 6285691406905 | **SENT** | 15:28:42 |
| 4 | `recap-harian` | 6285691406905 | **SENT** | 15:29:43 |

Isi slip yang diterima:

```
SLIP GAJI September 2026
Nama: John Doe (PGW001)

Hadir: 18
Izin: 2
Tidak Hadir: 2
Hari Kerja (pro-rata): 22

Gaji Pokok: Rp5.000.000
Potongan Tidak Hadir: -Rp454.546
Potongan Kasbon: -Rp750.000

GAJI BERSIH: Rp3.795.454

Dikirim otomatis dari Sistem Absensi RFID
```

Isi rekap yang diterima (2026-09-24):

```
REKAP KEHADIRAN 2026-09-24
Hadir : 7
Terlambat : 7
Izin : 0
Tidak Hadir : 0
—
Sistem Absensi RFID
```

> Catatan: karena semuanya dummy pola tetap, pada tanggal tertentu semua pegawai
> terlambat (7) — ini wajar dan justru membuktikan penghitungan berjalan.

## 6. Keterangan Tambahan (FAQ Sidang)

### Offline buffering / sinkronisasi otomatis — sudah jalan?

**Ya, sudah ada di firmware saat ini:**

- Saat WiFi/server mati, tap disimpan ke **buffer LittleFS** (uid + timestamp,
  tercatat aman saat listrik padam/reset) dan LCD menampilkan "OFFLINE — Antre: N".
- Saat koneksi pulih, buffer **dikirim otomatis** (retry tiap 3 detik),
  LCD "KIRIM BUFFER — Sisa: N".
- Timestamp offline memakai **NTP**; bila NTP gagal memakai jam boot (kurang akurat).

**Catatan untuk modul SD + RTC yang akan ditambah:**

- **RTC (DS3231)** → timestamps offline akurat meski tanpa NTP (jam boot tidak
  dipercaya). Bisa langsung menggantikan fallback waktu.
- **SD card** → pengganti/duplikasi LittleFS: kapasitas besar & mudah dibaca,
  tapi LittleFS sudah cukup untuk antrean ratusan-tibuan tap.

### WiFi server & ESP harus satu jaringan?

- **Produksi/tunnel: TIDAK.** ESP cukup WiFi berinternet, akses ke domain `my.id`.
- **Demo lokal tanpa tunnel:** harus satu jaringan (server bind `0.0.0.0`,
  ESP diset ke `http://IP-laptop:8000`, sesuaikan `KALIBRASI_*`/URL di firmware).

### ESP cuma bisa ke WiFi yang sudah didaftarkan, atau WiFi lain juga?

- ESP menyimpan **satu** kredensial (SSID + password, server, API key) di
  memori NVS (`Preferences`, bukan SD).
- Saat boot ia **otomatis terhubung ke WiFi tersimpan itu**. Tidak ada daftar
  multi-WiFi / roaming antar beberapa jaringan.
- Untuk ganti jaringan: pakai **Serial Monitor** → ketik `ssid <nama>`,
  `pass <sandi>`, `server <url>`, `apikey <key>`, lalu `save`; atau gunakan
  halaman konfigurasi bawaan ketika ESP belum punya kredensial: ESP jadi
  Access Point bernama **"ESP32-Absensi"** (sandi `12345678`) → buka
  `http://192.168.4.1` di HP/laptop → isi SSID/password/server di browser.

### Data buffer disimpan di ESP32 sendiri? Tanpa SD card jalan?

- **Ya, jalan tanpa SD card.** Buffer disimpan di **flash internal ESP32**
  lewat sistem file **LittleFS** (file `/buf.json`) — uid + timestamp + jumlah
  percobaan, tahan listrik padam/reset, kapasitas cukup untuk ratusan–ribuan tap.
- **SD card (yang akan ditambah)** hanya jadi pengganti/duplikat penyimpanan
  (kapasitas besar, mudah dicabut-dibaca). Fungsinya bukan keharusan.
- **RTC (DS3231)** berguna memperbaiki **akurasi timestamp offline**: saat ini
  firmware memakai NTP (akurat) dan fallback "jam boot" (kurang akurat) jika NTP
  gagal — dengan RTC, waktu offline selalu akurat walau tanpa internet.

### Di mana mengedit teks WhatsApp agar rapi?

| Pesan           | Lokasi edit                                                             |
| --------------- | ----------------------------------------------------------------------- |
| Slip gaji WA    | `app/Http/Controllers/PayrollController.php` → metode `waPayslipText()` |
| Rekap harian WA | `app/Console/Commands/SendDailyRecap.php` → variabel `$text`            |
| Format WAHA/API | `app/Services/WhatsApp/WahaDriver.php` (jangan ubah kecuali perlu)      |

Contoh hasil rekapan rapi setelah edit `$text`:

```
REKAP KEHADIRAN 2026-09-23
✅ Hadir         : 5
⏰ Terlambat   : 1
📝 Izin           : 2
❌ Tidak Hadir : 7
——————————————
Sistem Absensi RFID
```

> Catatan: pakai emoji sederhana + perataan kolom agar nyaman dibaca di WhatsApp.
