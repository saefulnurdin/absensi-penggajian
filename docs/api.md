# Panduan API untuk ESP32

Semua endpoint di bawah ini berada di prefix `/api` dan **wajib** menyertakan header:

```
X-API-KEY: <APP_API_KEY>
```

Nilai `APP_API_KEY` dibaca dari `.env` (default demo: `penggajian-rfid-demo-2026`).
Tanpa header yang benar server membalas **401 Unauthorized**.

Response selalu JSON. Field yang dipakai ESP32:

| Field      | Contoh            | Keterangan                       |
|------------|-------------------|----------------------------------|
| `success`  | `true`/`false`    | Status operasi                   |
| `employee` | `"John Doe"`      | Nama pegawai (untuk LCD)         |
| `employee_id` | `"PGW001"`      | ID pegawai                       |
| `status`   | `"HADIR"`         | HADIR / TERLAMBAT / PULANG / IZIN |
| `time`     | `"08:12:45"`      | Jam absensi                      |
| `late_minutes` | `5` (atau `null`) | Menit keterlambatan            |
| `message`  | `"Absensi masuk berhasil."` | Pesan untuk LCD  |
| `beep`     | `1`/`3`/`4`       | Pola buzzer: 1 sukses, 3 terlambat, 4 gagal |

## 1. Catat Absensi

```
POST /api/attendance
Content-Type: application/json
```

### Body

| Field         | Tipe        | Wajib | Keterangan                                    |
|---------------|-------------|-------|-----------------------------------------------|
| `uid`         | string ≤32  | pid   | UID kartu RFID (dipakai jika ada)             |
| `employee_id` | string ≤20  | pid   | Alternatif ID pegawai (mis. PIN/keypad)       |
| `mode`        | string      | tidak | `auto` (default), `masuk`, `pulang`, `izin`    |
| `source`      | string      | tidak | `RFID` (default), `KEYPAD`, `ADMIN`            |
| `timestamp`   | ISO 8601    | tidak | Waktu tap perangkat (buffer offline), contoh `2026-09-07T07:59:12` |

> Salah satu dari `uid` atau `employee_id` wajib ada.

### `timestamp` (buffer offline)

Normalnya server memakai waktu server sendiri (`now()`), jadi jam ESP32 tidak perlu
akurat. Bila perangkat sedang offline lalu baru online kembali, `timestamp` dikirim
agar waktu tap tercatat benar. Aturan validasi server:

- Waktu tidak boleh **lebih dari 3 hari ke masa lalu** → ditolak (422).
- Waktu tidak boleh **lewat 2 menit ke masa depan** (antisipasi selisih jam) → ditolak (422).
- Jika `timestamp` valid, semua perhitungan (hari kerja, keterlambatan, tanggal buka)
  memakai nilai itu; bila kosong/tidak dikirim, dipakai `now()` server.

Jadwal yang dipakai server: **tanggal khusus (override Hari Kerja) → shift pegawai →
jam global**. Ini menentukan "Bukan hari kerja", jam mulai, dan toleransi keterlambatan.

### Contoh — absen masuk via kartu (mode auto, tap pertama)

Request:

```json
{
  "uid": "A1B2C3D4",
  "mode": "auto"
}
```

Response `200`:

```json
{
  "success": true,
  "employee": "John Doe",
  "employee_id": "PGW001",
  "status": "HADIR",
  "time": "07:58:12",
  "late_minutes": null,
  "message": "Absensi masuk berhasil.",
  "beep": 1
}
```

### Contoh — absen masuk dari buffer offline (perangkat baru online)

```json
{
  "uid": "A1B2C3D4",
  "mode": "auto",
  "source": "RFID",
  "timestamp": "2026-09-07T07:58:12"
}
```

Waktu absensi yang tercatat = `07:58:12` (bukan waktu server saat dikirim).

### Contoh — terlamat (tap setelah toleransi)

Response `200` dengan `beep: 3`:

```json
{
  "success": true,
  "employee": "John Doe",
  "employee_id": "PGW001",
  "status": "TERLAMBAT",
  "time": "08:22:03",
  "late_minutes": 7,
  "message": "Terlambat 7 menit.",
  "beep": 3
}
```

### Contoh — tap kedua (pulang) dengan mode auto

```json
{ "uid": "A1B2C3D4", "mode": "auto" }
```

Response `200 `PULANG`:

```json
{
  "success": true,
  "employee": "John Doe",
  "employee_id": "PGW001",
  "status": "PULANG",
  "time": "17:05:40",
  "late_minutes": null,
  "message": "Absensi pulang berhasil.",
  "beep": 1
}
```

### Contoh — kartu tidak dikenal

Response `422` dengan `beep: 4`:

```json
{
  "success": false,
  "message": "RFID tidak terdaftar.",
  "employee": null,
  "beep": 4
}
```

Server juga mencatat satu email `admin-alert` (maksimal 1x per hari) dan menolak
request berikutnya hari itu (perilaku anti-spam).

**Pendaftaran kartu baru tanpa serial monitor:** setiap kali kartu tak dikenal
ditap, server menyimpan UID-nya ke tabel `rfid_unknowns`. Admin cukup membuka
menu **RFID Card → Kartu Baru Terdeteksi dari Perangkat**, memilih pegawai, dan
kartu langsung aktif (baris pending otomatis terhapus). Firmware ESP32 otomatis
mengirim UID tak dikenal ini saat POST `/api/attendance` — tidak perlu membaca
serial.

### Contoh — bukan hari kerja

Response `422`:

```json
{
  "success": false,
  "message": "Bukan hari kerja.",
  "employee": "John Doe",
  "beep": 4
}
```

### Contoh — duplikat / absen ganda

```json
{
  "success": false,
  "message": "Absensi pulang sudah tercatat.",
  "beep": 4
}
```

### Contoh — izin hari ini sudah tercatat

```json
{
  "success": false,
  "message": "Status IZIN sudah tercatat untuk tanggal tersebut.",
  "beep": 4
}
```

## 2. Cari Pegawai (untuk LCD info)

```
GET /api/employee/lookup?uid=A1B2C3D4
GET /api/employee/lookup?employee_id=PGW001
```

Response `200`:

```json
{
  "success": true,
  "employee": "John Doe",
  "employee_id": "PGW001",
  "position": "Staff"
}
```

Bila tidak ditemukan:

```json
{ "success": false, "message": "Tidak ditemukan." }
```

## 3. Status & Heartbeat Perangkat

### Cek status perangkat terakhir

```
GET /api/device/status
```

```json
{
  "success": true,
  "device": {
    "device_name": "ESP32-Absensi-1",
    "ip_address": "192.168.1.50",
    "last_seen_at": "2026-09-06T10:22:01.000000Z",
    "online": true
  }
}
```

### Kirim heartbeat (setiap ~30 detik)

```
POST /api/device/heartbeat
```

```json
{
  "device_name": "ESP32-Absensi-A1B2",
  "power_source": "MAINS",
  "battery_percent": 100,
  "wifi_rssi": -55
}
```

Semua field opsional. `power_source` = `MAINS` / `BATTERY`; `battery_percent` 0–100;
`wifi_rssi` −150–0. Data ini ditampilkan di menu **Perangkat ESP32**.

Response `200`:

```json
{
  "success": true,
  "message": "Perangkat online.",
  "last_seen_at": "2026-09-06T10:23:31.000000Z"
}
```

Heartbeat memperbarui `last_seen_at` dan menandai perangkat `online`. Health check
sinkronisasi normalnya menandai offline otomatis jika `last_seen_at` > 2 menit
(belum ada scheduler di prototype ini).

## 4. Health Check Teks

```
GET /api/ping
```

```
pong
```

## 5. Kode Buzzer (resolusi ESP32)

| `beep` | Arti                         | Usulan pola buzzer      |
|--------|------------------------------|-------------------------|
| 1      | Sukses (HADIR / PULANG)      | 1× pendek (100 ms)      |
| 3      | TERLAMBAT                   | 3× pendek               |
| 4      | Gagal / ditolak              | 1× panjang (500 ms)     |

Implementasi pola ada di sketsa firmware: `docs/esp32/esp32_absensi.ino`.

## 6. CATATAN Warming ESP32

- Waktu absensi **normalnya** ditentukan server (`now()`); ESP32 mengirim `timestamp`
  hanya untuk **buffer offline** → server memakainya bila masih masuk akal (lihat
  bagian `timestamp` di atas).
- UID dikirim sebagai string hex besar ≤32 karakter, contoh `"A1B2C3D4"`.
- Gunakan `Content-Type: application/json` dan selalu sertakan `X-API-KEY`.
- Untuk WiFi/server mati, tap disimpan ke **LittleFS** (beserta `timestamp`) dan
  dikirim otomatis saat online kembali dalam urutan antrean. Firmware juga
  hemat daya (deep sleep + tombol bangun) dan melaporkan daya/baterai/sinyal.