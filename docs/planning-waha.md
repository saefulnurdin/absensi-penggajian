# Planning: Notifikasi WhatsApp via WAHA (Self-Hosted)

> Dokumen perencanaan untuk fitur notifikasi WhatsApp pada aplikasi Absensi &
> Penggajian. Opsi yang dipertimbangkan: **WAHA (self-hosted, gratis, tanpa
> watermark)** dengan **driver abstrak** agar tetap bisa mundur ke Fonnte/Wablas
> hanya dengan mengubah `.env`.

---

## 1. Tujuan

- Kirim **rekap harian kehadiran harian** ke nomor admin.
- Kirim **slip gaji ringkas** ke `phone_wa` masing-masing karyawan setelah payroll diproses.
- Semua pesan berupa **teks** (tanpa gambar/lampiran) agar sesuai dengan batasan WAHA Core.

## 2. Arsitektur

```
[ESP32] --tap--> [Laravel API] --(queue)---> [WhatsAppNotifier job]
                                                    |
                                    +--------------- v -----------------+
                                    |  WAHA (Docker, port 3000)         |
                                    |  POST /api/sendText  text         |
                                    |  chatId = 62xxx@c.us              |
                                    +-----------------------------------+
                                                    |
                                                    v
                                       WhatsApp (nomor penerima)
```

- **WAHA** = REST API self-hosted di atas Docker yang membungkus protokol
  WhatsApp Web. Satu nomor WhatsApp = satu sesi. Sesi disimpan di volume agar
  tidak perlu scan QR ulang tiap restart.
- **Laravel** memanggil `POST http://<waha>:3000/api/sendText` dengan header
  `X-Api-Key` dan body `{ chatId, text, session }`.

### Syarat mutlak

| Kebutuhan | Keterangan |
|---|---|
| Docker | WAHA berjalan sebagai container (Docker Desktop di Windows / Docker di VPS) |
| Mesin menyala 24 jam | Sesi WAHA hanya hidup selama container berjalan |
| Nomor WA khusus (dedicated) | Wajib — bukan nomor utama; risiko banned menempel di nomor ini |
| Koneksi internet stabil | WhatsApp Web butuh koneksi; mati hotpot = pesan tertunda |

### Tempat menjalankan WAHA

Karena websitemu diakses lewat Cloudflare Tunnel dari mesin lokal (WiFi
hotspot), **WAHA cukup dijalankan di mesin yang sama dengan Laravel** (diakses
via `http://127.0.0.1:3000`). Tidak wajib ada VPS.

- **Cocok**: ruang demo tugas akhir / seminar — laptop menyala saat demo.
- **Kurang cocok**: kebutuhan 24/7 tanpa gangguan (butuh mesin selalu nyala
  atau VPS dengan Docker).

## 3. Batasan WAHA Core (gratis) — penting

| Kemampuan | Core (gratis) | Plus (±US$19/bln) |
|---|---|---|
| Jumlah sesi | 1 | Tanpa batas |
| Kirim teks / lokasi / kontak | ✅ | ✅ |
| Kirim media (gambar/file/audio) | ❌ | ✅ |
| Webhooks & monitoring sesi | ✅ | ✅ |
| Lisensi | Apache-2.0 | Berbayar (no license check untuk instance lama) |

Kebutuhan kita (rekap harian + slip teks) **cukup di Core gratis**. Jangan
berencana kirim gambar/file pakai Core.

## 4. Langkah Setup WAHA

### 4.1 Install Docker

- Windows: Docker Desktop (aktifkan WSL2 backend).
- Server Linux: `apt install docker.io docker-compose` atau paket resmi.

### 4.2 Jalankan container (Windows/PowerShell)

```powershell
docker pull devlikeapro/waha
docker run --rm -v "$(pwd)":/app/env devlikeapro/waha init-waha /app/env
```

Buka `.env` yang dihasilkan — catat `WAHA_DASHBOARD_USERNAME/PASSWORD` dan
`WAHA_API_KEY`.

Jalankan secara persisten (volume sesi supaya tidak scan QR ulang saat restart):

```powershell
docker run -d --name waha --restart unless-stopped `
  --env-file "$(pwd)/.env" `
  -v "$(pwd)/sessions:/app/.sessions" `
  -p 3000:3000 devlikeapro/waha
```

> Catatan: lokasi folder sesi bisa berubah antar versi WAHA. Cek dokumentasi
> storage versi yang terpasang (`WAHA_LOCAL_STORE_BASE_DIR`) sebelum mengandalkan
> volume — salah mount = QR ulang tiap restart.

### 4.3 Hubungkan nomor WA (satu kali, via QR)

1. Buka `http://localhost:3000/dashboard`, login dengan kredensial dari `.env`.
2. Start sesi `default` sampai status `SCAN_QR_CODE`.
3. Scan QR dengan WhatsApp nomor **dedicated** (WhatsApp → Perangkat Tertaut).
4. Status menjadi `WORKING`.

### 4.4 Uji kirim pertama

```powershell
curl.exe -X POST "http://localhost:3000/api/sendText" `
  -H "Content-Type: application/json" `
  -H "X-Api-Key: <WAHA_API_KEY>" `
  -d '{\"chatId\":\"628xxxxxxxxx@c.us\",\"text\":\"Tes dari WAHA\",\"session\":\"default\"}'
```

Nomor tujuan pakai format international **tanpa `+`**, tambah `@c.us`.

## 5. Integrasi Laravel (dibangun satu kali, driver bisa diganti)

### 5.1 Konfigurasi (`.env`)

```dotenv
WA_DRIVER=waha                  # waha | fonnte | log (untuk uji tanpa kirim beneran)
WAHA_BASE_URL=http://127.0.0.1:3000
WAHA_API_KEY=00000000000000000000000000000000
WAHA_SESSION=default
FONNTE_TOKEN=                   # diisi bila WA_DRIVER=fonnte
```

### 5.2 Komponen

| Komponen | Isi |
|---|---|
| `config/whatsapp.php` | mapping driver + config dari env |
| `App\Services\WhatsApp\WhatsAppDriver` (interface) | `send(string $phone, string $text): bool` |
| `WahaDriver` / `FonnteDriver` / `LogDriver` | implementasi masing-masing kanal |
| `App\Jobs\SendWhatsAppMessage` | job queue: format nomor, panggil driver, catat log hasil |
| `App\Models\NotificationLog` | log terkirim/gagal (dipakai bukti & troubleshooting) |

### 5.3 Data & trigger

| Fitur | Kolom/Setting | Trigger |
|---|---|---|
| Rekap harian | `settings: wa_admin_phone`, `wa_recap_time` | Scheduler command `attendance:wa-recap` tiap jam terpilih |
| Slip gaji | `employees.phone_wa` | Setelah `PayrollController::process` / `generate` |

### 5.4 Alur antrian

Semua kiriman lewat queue (`QUEUE_CONNECTION=database`) supaya tidak memblokir
request absensi/payroll.

## 6. Strategi Anti-Banned

1. **Nomor dedicated** untuk gateway — bukan nomor utama. Ini mitigasi #1 (terkuat).
2. **Hanya kirim ke kontak yang sudah saling kenal / opt-in** (karyawan mencantumkan
   `phone_wa` = setuju menerima slip). Jangan pernah broadcast ke nomor asing.
3. **Volume rendah & pesan personal** — rekap harian 1 pesan/hari; slip per karyawan
   saat penggajian. Hindari pesan identik berulang.
4. **Warm-up**: minggu pertama kirim hanya 1–2 pesan/hari ke nomor yang dikenal.
5. **Rate wajar**: tetap jauh di bawah batas komunitas (≪ beberapa puluh pesan/menit);
   tambahkan jeda antar slip (mis. 2–5 dtk) saat kirim massal payroll.
6. **Monitoring sesi**: aktifkan webhook `session.status` (atau cek dashboard
   berkala). Jika WAHA tiba-tiba minta QR ulang / status `FAILED` / log out →
   berhenti mengirim dan evaluasi dulu.
7. **Tidak ada jaminan 100%** — protokol WhatsApp Web tidak untuk otomasi; risiko
   tetap ada. Nomor khusus menerima risiko ini.

## 7. Risiko & Mitigasi

| Risiko | Level | Mitigasi |
|---|---|---|
| Nomor gateway diblokir WhatsApp | Sedang (turun drastis bila volume kecil & penerima dikenal) | Nomor dedicated; warm-up; monitoring sesi |
| Sesi WAHA putus saat restart/off | Sedang | `--restart unless-stopped` + volume sesi |
| Laptop mati → notifikasi tidak jalan | Sedang (untuk demo) | Jalan hanya saat laptop nyala; dokumentasikan keterbatasan ini |
| WAHA Core tak bisa kirim media | Rendah (kita cuma teks) | Rancang pesan slip tanpa lampiran |
| Beda versi volume folder sesi | Rendah | Catat versi WAHA & lokasi store saat setup |

## 8. Urutan Pekerjaan (Milestone)

1. **Payroll pro-rata** — gaji bisa di-generate kapan saja (opsi "sampai tanggal X")
   → dasar pengujian slip gaji tengah bulan. + test.
2. **Data dasar**: migration `employees.phone_wa`; setting `wa_admin_phone` &
   `wa_recap_time`; form pegawai + halaman settings.
3. **Driver & queue**: `config/whatsapp.php`, interface + `WahaDriver` /
   `FonnteDriver` / `LogDriver`, job `SendWhatsAppMessage`, migration
   `notification_logs`, `php artisan test`.
4. **Rekap harian**: command `attendance:wa-recap` + registrasi scheduler.
5. **Slip gaji via WA**: hook di `PayrollController::process` → antre slip teks
   ke `phone_wa` karyawan (dengan jeda antar kirim).
6. **Uji end-to-end**: driver `log` dulu (tanpa WA), lalu `waha`, lalu snapshot
   untuk dokumentasi tugas akhir (screenshot dashboard WAHA, log terkirim).

## 9. Bahan Dokumentasi Tugas Akhir

- Arsitektur: diagram blok ESP32 → Laravel (queue) → WAHA → WhatsApp.
- Alur axios anti-ban (dedicated number, warm-up, opt-in).
- Kode inti: `WhatsAppDriver`, `WahaDriver`, `SendWhatsAppMessage`, scheduler.
- Bukti uji: screenshot hasil kirim, tabel `notification_logs`, dashboard WAHA.

## 10. Keputusan Terbuka

- [ ] Konfirmasi **tempat menjalankan WAHA**: laptop demo (`127.0.0.1:3000`)
      atau VPS (bila tersedia).
- [ ] Tetapkan nomor dedicated (salah satu dari 2 nomor yang kamu punya).
- [ ] Jam rekap harian (mis. 17:00) & nomor admin penerima rekap.
- [ ] Format slip WA (ringkas, tanpa lampiran): nama, periode (tanggal potong),
      hadir/izin/bolos, pokok, potongan, bersih.

---

## 11. Status Implementasi (2026-09-19)

Fitur sudah **dibangun dan diuji** di sisi Laravel (91 test hijau, 282 assertions):

| Item | Status | Catatan |
|---|---|---|
| Payroll pro-rata (`as_of_date`) | ✅ | `AttendanceService::monthlyRecap(..., ?string $asOf)`; `PayrollService::generateForPeriod/generateForEmployee`; input `as_of_date` di form payroll; kolom `payrolls.as_of_date` |
| `employees.phone_wa` | ✅ | migration + validasi `regex:/^\+?[0-9]{8,15}$/` + field form pegawai |
| Setting `wa_admin_phone`, `wa_recap_time` | ✅ | constants + DEFAULTS + halaman Pengaturan |
| `whatsapp_logs` | ✅ | tabel sengaja bernama `whatsapp_logs` (bukan `notification_logs`); model `WhatsAppLog` dipaksa `protected $table` |
| Driver | ✅ | `interface WhatsAppDriver` di `app/Services/WhatsApp/Contracts`; `WahaDriver`, `FonnteDriver`, `LogDriver`, `WhatsAppService`, `WaNumber` |
| Job | ✅ | `App\Jobs\SendWhatsAppMessage` (queue `database`), catat SENT/FAILED/SKIPPED, retry 3x |
| Rekap harian | ✅ | command `wa:recap` (bukan `attendance:wa-recap`), scheduler `everyThirtyMinutes` + cek jam dari setting; opsi `--date` untuk uji manual |
| Slip gaji via WA | ✅ | hook `process` & `sendMany`; jeda antar kirim 5 dtk (sendMany) |
| Konfigurasi | ✅ | env `WA_DRIVER` (default `log`), `WAHA_BASE_URL`, `WAHA_API_KEY`, `WAHA_SESSION`, `FONNTE_TOKEN` di `.env` & `.env.example` |
| Test | ✅ | `WhatsAppServiceTest`, `SendWhatsAppMessageTest`, `SendDailyRecapTest` + test pro-rata di `PayrollServiceTest` |

Perbedaan kecil dari rencana awal: nama command `wa:recap`, table `whatsapp_logs`,
penambahan `Contracts\WhatsAppDriver`, helper `App\Support\WaNumber`, dan metode
`WhatsAppService::send()` menormalkan nomor 08xxx → 628xxx otomatis.

### Langkah agar benar-benar terkirim ke nomor WA

1. Tentukan driver: `WA_DRIVER=waha` (WAHA) atau `fonnte`.
2. Jalankan worker queue agar job slip/rekap tereksekusi:
   `php artisan queue:work` (dan untuk scheduler: `php artisan schedule:work`).
3. Isi nomor admin di halaman Pengaturan & `phone_wa` tiap karyawan.
4. Tes rekap manual: `php artisan wa:recap --date=YYYY-MM-DD`.
5. Tes slip: proses payroll → cek tabel `whatsapp_logs` (SENT/FAILED).