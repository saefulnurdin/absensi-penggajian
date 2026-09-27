# Dokumen Perencanaan & Arsitektur

Prototype Sistem Absensi & Penggajian RFID — Tugas Akhir.

## 1. Gambaran Sistem

Sistem terdiri dari dua bagian utama:

1. **Perangkat keras (ESP32 + MFRC522)** — membaca kartu RFID, menampilkan pesan di
   LCD 16x2, memberi umpan balik buzzer, dan mengirim HTTP request ke server.
2. **Aplikasi web (Laravel)** — menerima data absensi, mengelola master data pegawai,
   kartu, hari kerja & pengaturan, menampilkan rekap/kalender, menghitung gaji,
   dan mengirim notifikasi email.

Alur dasar absensi:

```
Kartu di-tap
  -> ESP32 membaca UID
  -> POST /api/attendance {"uid":"A1B2C3D4","mode":"auto"} + X-API-KEY
  -> Server memvalidasi kartu & pegawai, catat HADIR/TERLAMBAT (masuk) atau PULANG
  -> Respon JSON { success, status, message, beep }
  -> ESP32 menampilkan nama/jam di LCD dan membunyikan buzzer sesuai kode beep
```

## 2. Arsitektur Teknis

```
┌─────────────┐   HTTPS    ┌──────────────────────────────────────────────┐
│  ESP32      │ ─────────▶ │  Laravel 13                                  │
│ MFRC522     │  JSON      │  routes/api.php  (auth: X-API-KEY)           │
│ LCD 16x2    │            │  routes/web.php  (auth: session + admin)     │
│ Buzzer      │ ◀───────── │  Service layer: Attendance, Payroll,         │
└─────────────┘   JSON     │                Notification                  │
                          │  MySQL            SQLite (test)               │
                          │  Mail: log / Mailtrap  ->  tabel email_logs   │
                          └──────────────────────────────────────────────┘
```

- **API (untuk ESP32):** `AttendanceApiController`, `DeviceApiController`,
  middleware `EnsureApiKey` (header `X-API-KEY` = `APP_API_KEY`).
- **Web (untuk admin):** seluruh route dilindungi `auth` + `admin`
  (middleware `EnsureIsAdmin`, cek `is_admin`).
- **Service layer** memisahkan logika bisnis dari controller sehingga mudah diuji.

## 3. Skema Basis Data (ERD ringkas)

```
users            employees (1) ──< (n) attendance
  id, name         id                id, employee_id, attendance_date (unique per pegawai/hari)
  email, password  employee_id       time_in, time_out, status, late_minutes
  is_admin         name              source (RFID|KEYPAD|ADMIN), note, edited_by, edit_reason
                   position
                   base_salary
                   status (Aktif|Nonaktif)
                   hire_date, email, phone

employees (1) ──< (1) rfid_cards      employees (1) ──< (n) payrolls
                     id                     id, employee_id, period (YYYY-MM, unique)
                     employee_id, uid       work_days, hadir_count, late_count
                     is_active, note        izin_count, absent_count, incomplete_count
                                            base_salary, daily_salary, deduction, net_salary
                                            status (DRAFT|DIPROSES)

work_days         settings          device_logs             email_logs
  day_index 0..6     key (unique)      device_name           type, recipient, subject
  day_name          value              ip_address            body_preview
  is_work_day                           last_seen_at          status (SENT|FAILED|QUEUED)
                                        online                related_type, related_id

rfid_unknowns
  id, uid (unique), ip_address, first_seen_at, last_seen_at, hits
```

### Detail kolom penting

- `attendance.attendance_date` — DATE; unik per `(employee_id, attendance_date)`.
- `attendance.status` — `HADIR | TERLAMBAT | IZIN | ABSENSI TIDAK LENGKAP | TIDAK HADIR`.
- `settings` (key-value): `company_name`, `work_start`, `work_end`,
  `break_start`, `break_end`, `late_tolerance_minutes`.
- `work_days`: `day_index` mengikuti `date('w')` PHP — **0 = Minggu .. 6 = Sabtu**.
  Default hari kerja Senin–Jumat.

## 4. Algoritma Inti

### 4.1 Menentukan hari kerja (`AttendanceService::workDates`)

```
dates = []
untuk tanggal = 1 .. akhir bulan:
    jika weekday(tanggal) ada di daftar is_work_day: dates.append(tanggal)
kembalikan dates
```

### 4.2 Status absensi masuk (`AttendanceService::checkIn`)

```
jika sudah ada time_in hari ini      -> tolak (duplikat)
jika status hari ini IZIN            -> tolak
time = jam tap
batas = work_start + late_tolerance_minutes
late = batas - time   (menit, negatif jika lebih awal)
status = late > 0 ? TERLAMBAT : HADIR
late_minutes = late > 0 ? late : null
```

> Catatan implementasi: `diffInMinutes($other)` di Carbon mengembalikan `$other - $this`,
> sehingga perhitungan menit keterlambatan ditulis
> `batas->diffInMinutes(time)` = `time - batas`.

### 4.3 Absensi pulang (`checkOut`)

```
jika tidak ada time_in         -> tolak ("Absensi masuk tidak ditemukan")
jika time_out sudah ada        -> tolak (duplikat pulang)
time_out = jam tap; status tetap (TERLAMBAT dipertahankan)
```

### 4.4 Izin (`takeLeave`)

```
jika sudah IZIN   -> tolak
jika sudah time_in -> tolak ("sudah absen hadir")
simpan status IZIN, source, note
```

### 4.5 Rekap bulanan (`monthlyRecap`)

Untuk tiap tanggal hari kerja:

| Kondisi di `attendance`                        | Status rekap                | Penghitung |
|------------------------------------------------|-----------------------------|------------|
| status = IZIN                                  | IZIN                        | izin++     |
| time_in ada, time_out tidak, tanggal masa lalu | ABSENSI TIDAK LENGKAP       | incomplete++ |
| time_in + time_out ada (status TERLAMBAT)      | TERLAMBAT (dihitung hadir)  | hadir++, terlambat++ |
| time_in + time_out ada (status HADIR)          | HADIR                       | hadir++    |
| tidak ada record                               | TIDAK HADIR                 | tidakHadir++ |

Kunci lookup adalah `attendance_date->toDateString()` agar kunci string cocok dengan
`workDates` (perbaikan penting karena cast `date` menghasilkan objek Carbon).

### 4.6 Penggajian (`PayrollService::generateForEmployee`)

```
workDays       = jumlah hari kerja bulan tsb      (dari WorkDay + tanggal khusus, BUKAN angka paten)
dailySalary    = base_salary / workDays           (dibulatkan)
deduction      = tidakHadir x dailySalary
kasbonDeduction = total kasbon APPROVED/PAID dgn deduct_period = periode ini
netSalary      = max(0, base_salary - deduction - kasbonDeduction)
```

- `updateOrCreate` per `(employee_id, period)` — regenerate aman, tidak duplikat.
- Status `DIPROSES` dipertahankan saat regenerate (nilai lama diambil lagi).
- Periode `2026-02`: 20 hari kerja → gaji bersih = gaji pokok − potongan absen.
- `as_of_date` (opsional, YYYY-MM-DD): pro-rata — hari kerja dihitung sampai tanggal tsb.
- Jumlah hari kerja berubah per bulan (bukan di-hardcode 22); bisa dimodifikasi lewat
  menu **Hari Kerja** (is_work_day per weekday) dan **Tanggal Khusus** (override).

### 4.7 Mode auto multi-tap di API

- `mode=auto`: jika belum ada `time_in` hari ini → **masuk**; jika sudah ada → **pulang**.
- `mode=masuk` / `mode=pulang` / `mode=izin` memaksa arah tertentu.
- Respon punya field `beep`: `1` = sukses, `3` = terlambat, `4` = gagal (kartu
  tak dikenal / bukan hari kerja / duplikat / nonaktif).

### 4.8 Notifikasi email (`NotificationService`)

Setiap notifikasi menerjemahkan ke `Mailable` lalu menulis baris di `email_logs`
(`type`, `recipient`, `subject`, `status` SENT/FAILED, relasi polymorfik).

| Jenis                     | `type` (email_logs)      | Pemicu                              |
|---------------------------|--------------------------|-------------------------------------|
| Hadir                     | `attendance:hadir`       | check-in tidak terlambat            |
| Terlambat                 | `attendance:terlambat`   | check-in setelah toleransi          |
| Pulang                    | `attendance:pulang`      | check-out                           |
| Izin                      | `attendance:izin`        | take-leave (API)                    |
| Tidak hadir               | `attendance:tidak_hadir` | (dipanggil manajemen/CLI)           |
| Slip gaji                 | `payslip`                | kirim slip per pegawai              |
| Gaji diproses             | `payroll-processed`      | proses payroll → status DIPROSES    |
| Kartu tak dikenal (anomali) | `admin-alert`          | kartu/ID tidak dikenal, 1x/hari      |

### 4.9 Kasbon (`Kasbon` model)

```
Buat (PENDING) -> Setujui (APPROVED, approved_at) -> saat payroll periode
deduct_period dihitung ulang, kasbon dipotong dari gaji dan berstatus PAID.
```

- Tabel `kasbons`: employee_id, amount, note, deduct_period (YYYY-MM), status
  (PENDING/APPROVED/CANCELLED/PAID), approved_at, paid_in_period.
- `PayrollService::aggregateKasbon` menjumlah kasbon `APPROVED`/`PAID` dgn
  `deduct_period` = periode yang sedang dihitung → dimasukkan ke `payrolls.kasbon_deduction`.
- Filter menyertakan PAID agar **regenerasi periode yang sama tetap konsisten**
  (tidak menggandakan, tidak kehilangan angka).
- Kasbon PAID tidak bisa dibatalkan/dihapus.

### 4.10 Keamanan

- Seluruh `routes/api.php` melewati middleware `EnsureApiKey`. Tanpa `X-API-KEY`
  yang sesuai `APP_API_KEY` → 401.
- Seluruh `routes/web.php` (kecuali login/logout) melewati `auth` + `admin`.
- `AuthController` menolak login user non-admin dengan error "Akun tidak memiliki akses."

## 5. Daftar Route

### API (header `X-API-KEY` wajib)

| Method | URI                    | Fungsi                              |
|--------|------------------------|-------------------------------------|
| POST   | `/api/attendance`      | Catat absensi (masuk/pulang/izin/auto) |
| GET    | `/api/employee/lookup` | Cari pegawai dari UID/ID (untuk LCD) |
| GET    | `/api/device/status`   | Status perangkat terakhir            |
| POST   | `/api/device/heartbeat`| Heartbeat ESP32 (update online/last_seen) |
| GET    | `/api/ping`            | Health check                        |

### Web (login admin)

**Auth**: `GET/POST /login`, `POST /logout`.

| Area       | Route                                                     |
|------------|-----------------------------------------------------------|
| Dashboard  | `GET /`                                                   |
| Pegawai    | `GET /employees`, `GET /employees/create`, `POST /employees`, `GET/PUT /employees/{id}/edit`, `DELETE /employees/{id}` |
| RFID Card  | `GET /rfid-cards`, `POST /rfid-cards`, `POST /rfid-cards/{id}/assign`, `POST /rfid-cards/{id}/toggle`, `DELETE /rfid-cards/{id}` |
| Hari Kerja | `GET /work-days`, `PUT /work-days`                        |
| Pengaturan | `GET /settings`, `PUT /settings`                          |
| Absensi    | `GET /attendance`, `GET /attendance/create`, `POST /attendance`, `GET/PUT /attendance/{id}/edit`, `DELETE /attendance/{id}` |
| Rekap      | `GET /rekap`, `GET /kalender`                             |
| Payroll    | `GET /payroll`, `POST /payroll/generate`, `GET /payroll/{id}`, `GET /payroll/{id}/slip`, `POST /payroll/{id}/process`, `POST /payroll/send-many` |
| Sistem     | `GET /devices`, `GET /email-logs`                         |

## 6. Pengujian

Test menggunakan SQLite file, bukan `:memory:` (hindari ketidakcocokan koneksi).
Persiapan:

```bash
php artisan migrate:fresh --env=testing --seed
php vendor/bin/phpunit
```

Hasil: **55 test, 179 assertion, 100% pass**.

### Test case (pemetaan ke bab pengujian)

**Auth (`AuthTest`)**
1. Login admin berhasil → redirect ke dashboard.
2. Login dengan password salah → error.
3. Login non-admin ditolak.
4. Logout → kembali ke halaman login.

**Absensi service (`AttendanceServiceTest`)**
5. Check-in tepat waktu → HADIR, tanpa late_minutes.
6. Check-in dalam batas toleransi → HADIR.
7. Check-in setelah toleransi → TERLAMBAT + late_minutes sesuai.
8. Check-in ganda di hari sama → ditolak (DomainException).
9. Check-out tanpa check-in → ditolak.
10. Check-out ganda → ditolak.
11. Izin + duplikat izin ditolak.
12. Izin memblokir check-in di hari yang sama.
13. Batas `minutesLate`: 07:59→0, 08:15→0, 08:20→5, toleransi 0.
14. `workDates` hanya menghitung hari kerja terkonfigurasi (September 2026 = 22 hari).
15. Rekap bulanan menghitung hadir/terlambat/izin/tidak hadir.
16. Rekap menandai ABSENSI TIDAK LENGKAP untuk check-in tanpa check-out (tanggal lalu).
17. Rekap mengecualikan akhir pekan.

**Payroll (`PayrollServiceTest`)**
18. Perhitungan Februari 2026: 20 hari kerja, potongan 17×250.000 → net 750.000.
19. Generate ganda per periode tidak membuat duplikat.
20. Regenerate memperbarui total setelah data absensi berubah.
21. Status DIPROSES dipertahankan setelah regenerate.
22. Pegawai nonaktif dikecualikan dari generate.

**API absensi (`AttendanceApiTest`)**
23. 401 tanpa X-API-KEY.
24. Resolusi kartu: absen masuk via UID.
25. Disebutkan oleh employee_id.
26. Hak akses: pegawai nonaktif ditolak.
27. Mode auto: tap pertama masuk, tap kedua pulang.
28. Tap ketiga ditolak.
29. Check-in terlambat → beep 3 + pesan terlambat.
30. Bukan hari kerja → beep 4 + pesan.
31. Mode izin tercatat.
32. Kartu tidak dikenal → tolak, admin alert tercatat.
33. Kartu nonaktif → tolak.
34. Mode izin saat sudah hadir → tolak.
35. Validasi body kosong (tanpa uid/employee_id ≠ 500).
36. Heartbeat perangkat memperbarui `last_seen_at`.
37. Device status + employee lookup.

**RFID Card (`RfidCardTest`)**
38. Simpan kartu baru.
39. Cetak status kartu default aktif.
40. Kartu dengan UID duplikat ditolak.
41. Toggle nonaktif → tidak bisa dipakai absensi.
42. Delete kartu.

**Render halaman (`PageRenderTest`)**
43–52. Halaman admin (dashboard, pegawai, RFiD, hari kerja, absensi, rekap, kalender,
payroll, devices, email-logs, settings) merender status 200 & judul benar.
(termasuk filter & halaman kosong).

**Email (`EmailNotificationTest`)**
53. Check-in HADIR → email_logs `attendance:hadir` SENT.
54. Check-in terlambat → `attendance:terlambat`.
55. Payslip + payroll-processed → `payslip` & `payroll-processed` tercatat.
- Kartu tak dikenal alert dibatasi 1x/hari.
- Halaman `/email-logs` menampilkan isi log.

## 7. Batasan Prototype

- Keterlambatan tidak memotong gaji (hanya mencatat informasi).
- Email dikirim asli via **Gmail SMTP** (App Password) untuk semua notifikasi;
  masih bisa masuk folder Spam bila volume besar (batas ±500 penerima/hari).
- Sinkronisasi waktu disarankan memakai RTC/NTP di sisi ESP32; server memakai
  waktu lokal (`APP_TIMEZONE`).
- Belum ada tampilan pegawai (khusus admin; absensi ESP32 melalui API).

## 8. Rencana Selanjutnya

- [x] Firmware ESP32 lengkap (RC522 + LCD + buzzer + heartbeat + retry offline).
- [x] Panduan API untuk ESP32 (`docs/api.md`).
- [x] Provisioning WiFi tanpa laptop (captive portal 192.168.4.1 + NVS).
- [x] Daftar kartu baru lewat web (tabel `rfid_unknowns` + menu RFID Card).
- [x] Integrasi Mailtrap/SMTP sungguhan (credential sandbox di `.env`).
- [x] Shift per pegawai + tanggal khusus (override) hari kerja & jam masuk/pulang.
- [x] Jenis pegawai KARTAP / KONTRAK / MAGANG dengan rentang masa kerja.
- [x] Firmware v2: NTP, jam LCD, buffer offline (LittleFS) + kirim otomatis,
      deep sleep + bangun tombol, laporan daya utama/baterai/sinyal.
- [ ] Laporan excel/PDF rekap & slip gaji.
- [ ] Naikkan kapasitas email (mis. auto-switch Brevo/SES) bila lebih dari ±500 email/hari.