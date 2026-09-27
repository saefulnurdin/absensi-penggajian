# Sistem Absensi & Penggajian RFID Berbasis ESP32

Prototype Tugas Akhir: sistem absensi karyawan berbasis **RFID (MFRC522/RC522)** dengan
**ESP32** sebagai perangkat keras, dan aplikasi web **Laravel** sebagai backend untuk
pencatatan absensi, rekap kehadiran, dan penggajian bulanan.

## Fitur

- **Absensi RFID via ESP32** — endpoint `POST /api/attendance`, dilindungi API key.
  Mode `auto`: tap pertama = masuk, tap kedua = pulang. Perangkat boleh mengirim
  `timestamp` (buffer offline) dan server tetap menjadi otoritas waktu (maks. 3 hari lalu).
- **Deteksi keterlambatan** — status HADIR / TERLAMBAT berdasarkan jam masuk + toleransi.
- **Hari kerja dinamis** — pola mingguan (menu *Hari Kerja*) **+ tanggal khusus/override**
  (libur nasional, kerja tambahan, jam berbeda pada tanggal tertentu).
- **Shift per pegawai** — menu *Shift Kerja* (jam mulai/selesai + toleransi sendiri);
  pegawai tanpa shift memakai jam global. Prioritas jadwal: tanggal khusus > shift > global.
- **Rekap & kalender absensi** — status per hari: HADIR, TERLAMBAT, IZIN,
  ABSENSI TIDAK LENGKAP, TIDAK HADIR.
- **Penggajian otomatis** — potongan harian untuk ketidakhadiran di hari kerja,
  slip gaji dan email notifikasi (log mailer / Mailtrap).
- **Jenis pegawai** — KARTAP / KONTRAK / MAGANG dengan rentang masa kerja
  (informasi & validasi; tidak mengubah perhitungan gaji).
- **Notifikasi email** — konfirmasi hadir/terlambat/pulang/izin, slip gaji,
  gaji diproses, alert kartu tidak terdaftar (dibatasi 1x/hari).
- **Pendaftaran kartu lewat web** — UID kartu baru yang ditap muncul otomatis di
  menu *RFID Card → Kartu Baru Terdeteksi*; tidak perlu serial monitor.
- **Provisioning WiFi produksi** — firmware menyimpan SSID/password di flash;
  saat WiFi gagal, ESP32 menjadi hotspot `ESP32-Absensi` (192.168.4.1) untuk isi
  WiFi baru dari HP tanpa laptop.
- **Firmware hemat daya & offline-first** — sinkronisasi NTP, jam di LCD 16x2,
  buffer offline di LittleFS (tap tersimpan & otomatis terkirim saat online),
  deep sleep saat idle + bangun pakai tombol, laporan daya utama/baterai & sinyal
  WiFi ke menu *Perangkat ESP32*.
- **Halaman admin** — Pegawai, RFID Card, Hari Kerja, Shift Kerja, Data Absensi,
  Rekap, Kalender, Penggajian, Perangkat ESP32, Log Email, Pengaturan.
- **Keamanan** — autentikasi admin plus middleware `admin`; API key di header
  `X-API-KEY` untuk seluruh route `api/*`.

## Tech Stack

| Bagian       | Teknologi                                  |
|--------------|--------------------------------------------|
| Backend      | Laravel 13 (PHP 8.5)                       |
| Database     | MySQL 8.0 (produksi), SQLite (test)        |
| Perangkat    | ESP32 + MFRC522 (RC522), LCD 16x2, buzzer  |
| Email        | Gmail SMTP (App Password, ±500/hari), log email |

## Kebutuhan Sistem

- PHP 8.5 + Composer
- MySQL 8.0 (atau SQLite untuk testing)
- ESP32 + modul RFID RC522 (untuk simulasi perangkat nyata)

## Instalasi

```bash
git clone <repo-url> penggajian
cd penggajian

composer install

copy .env.example .env        # Windows:  copy .env.example .env
php artisan key:generate

# Konfigurasi DB di .env, lalu:
php artisan migrate --seed
php artisan serve              # http://localhost:8000
```

## Akun & Data Demo

Seeder membuat akun admin dan 5 pegawai contoh (masing-masing punya 1 kartu RFID aktif,
data absensi bulan berjalan, serta 1 perangkat ESP32).

| Akun / Kartu | Nilai                    |
|--------------|--------------------------|
| Admin        | `admin@absensi.local` / `admin1234` |
| API Key      | `penggajian-rfid-demo-2026` |
| Contoh UID   | `A1B2C3D4` (John Doe), `E5F6A7B8` (Jane Doe), `C9D0E1F2`, `3A4B5C6D`, `7E8F9A0B` |

> API key dikonfigurasi melalui `APP_API_KEY` di `.env`. Wajib dikirim pada header
> `X-API-KEY` untuk semua request ke endpoint `api/*`.

## Menjalankan Test

Test memakai basis data SQLite file `database/testing.sqlite` (bukan `:memory:` agar
koneksi query test dan aplikasi konsisten):

```bash
php artisan migrate:fresh --env=testing --seed   # siapkan DB test (sekali)
php vendor/bin/phpunit
```

Semua test case wajib (bagian pengujian) terdapat di `tests/Feature/` dan lulus 76/76.
Daftar lengkap: [`docs/planning.md`](docs/planning.md) (bagian Pengujian).

## Struktur Proyek

```
app/
  Models/        Eloquent models (Employee, Attendance, RfidCard, Payroll, ...)
  Services/
    AttendanceService.php    Logika absensi (masuk/pulang/izin, menit terlambat, rekap)
    PayrollService.php       Perhitungan gaji bulanan
    NotificationService.php  Email notifikasi + catat email_logs
  Http/Controllers/
    Api/                     AttendanceApiController, DeviceApiController (untuk ESP32)
    Auth, Employee, RfidCard, WorkDay, Setting, Attendance, Recap, Calendar,
    Payroll, Device, EmailLog, Dashboard
  Mail/                      mailable: attendance, payslip, payroll-processed, admin-alert
  Http/Middleware             EnsureIsAdmin, EnsureApiKey
database/
  migrations/                 skema 10+ tabel
  seeders/                    DatabaseSeeder (data demo)
docs/
  planning.md                arsitektur, ERD, algoritma, daftar route, pengujian
  api.md                     panduan endpoint API untuk ESP32
  esp32/esp32_absensi.ino    sketsa firmware ESP32
tests/Feature/               76 test case
```

## Dokumentasi Lengkap

- [Dokumen Perencanaan & Arsitektur](docs/planning.md)
- [Panduan API (ESP32)](docs/api.md)
- [Sketsa Firmware ESP32](docs/esp32/esp32_absensi.ino)