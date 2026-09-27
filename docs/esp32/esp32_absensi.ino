/*
 * ============================================================================
 *  Sketsa Firmware ESP32 v2.0 â€” Absensi RFID (Tugas Akhir)
 * ----------------------------------------------------------------------------
 *  Komponen:
 *    - MFRC522 / RC522    (pembaca kartu RFID)
 *    - LCD 16x2 + I2C     (layar pesan, dengan jam real-time)
 *    - Buzzer aktif-rendah (umpan balik suara)
 *
 *  Fitur:
 *    1. Kredensial WiFi di flash (Preferences/NVS) + captive portal
 *       (AP "ESP32-Absensi" -> 192.168.4.1) untuk konfigurasi lewat HP.
 *    2. NTP (time.google.com) -> jam dinding di LCD + "timestamp" absensi.
 *       Saat online server tetap menjadi otoritas waktu; NTP dipakai untuk
 *       buffer OFFLINE supaya waktu tap tercatat benar.
 *    3. BUFFER OFFLINE (LittleFS): bila WiFi/server mati, tap disimpan ke
 *       flash (uid + timestamp) dan dikirim otomatis saat online kembali,
 *       dalam urutan antrean. Simpanan aman saat mati listrik/reset.
 *    4. DEEP SLEEP: jika tidak ada kartu dalam SELIDLE detik, ESP32 tidur
 *       (hemat daya, siap pakai saat padam listrik / pakai baterai).
 *       Bangun dengan menekan tombol WAKE_PIN. Setelah reboot, langsung
 *       konek WiFi lagi dan memproses kartu + menyalurkan buffer.
 *    5. Daya & baterai (opsional): lapor MAINS/BATTERY + persen baterai
 *       ke heartbeat; server menampilkan status di menu "Perangkat ESP32".
 *    6. Kartu tak dikenal tetap dikirim -> terdeteksi di menu
 *       "RFID Card -> Kartu Baru Terdeteksi" tanpa serial monitor.
 *
 *  Konfigurasi opsional (ubah di bawah sesuai wiring):
 *    CONFIG_PIN        - tombol setup (tahan saat power ON -> paksa portal)
 *    WAKE_PIN          - tombol bangun untuk deep sleep
 *    POWER_MAINS_PIN   - deteksi listrik utama (HIGH saat ada PLN) atau -1
 *    BATTERY_ADC_PIN   - pembagi tegangan baterai (GPIO ADC) atau -1
 *
 *  Waktu default: WIB (UTC+7). Ubah NTP_TZ_OFFSET bila perlu.
 * ============================================================================
 */

#include <SPI.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <DNSServer.h>
#include <Preferences.h>
#include <WebServer.h>
#include <LittleFS.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ---------------------------------------------------------------------------
//  KONFIGURASI AWAL (dipakai bila nilai di flash belum ada)
// ---------------------------------------------------------------------------
const char *DEFAULT_SERVER = "https://app.absensi-penggajian.my.id";
const char *DEFAULT_APIKEY = "penggajian-rfid-demo-2026";

const char *AP_NAME = "ESP32-Absensi";
const char *AP_PASS = "12345678";

const long NTP_TZ_OFFSET = 7 * 3600; // WIB (UTC+7)
const int NTP_DAYLIGHT = 0;
const char *NTP_SERVER1 = "time.google.com";
const char *NTP_SERVER2 = "id.pool.ntp.org";

// Tombol setup OPSIONAL (long-press saat power ON -> paksa portal). -1 = mati.
#define CONFIG_PIN -1

// ---------------------------------------------------------------------------
//  PIN PERANGKAT KERAS
// ---------------------------------------------------------------------------
#define RFID_SS_PIN 5
#define RFID_RST_PIN 27
#define BUZZER_PIN 26
#define LCD_ADDR 0x27
#define LCD_COLS 16
#define LCD_ROWS 2
#define LCD_SDA 21
#define LCD_SCL 22

// Mode hemat daya: setelah SELIDLE detik tanpa aktivitas -> deep sleep.
// PENTING: bangun KECUALI tombol terpasang ke WAKE_PIN-GND. Tanpa tombol,
// perangkat tidur selamanya (LCD gelap). 0 = sleep dimatikan (disarankan dulu).
#define WAKE_PIN 33   // tombol bangun (active LOW, gunakan pull-up)
#define SELIDLE_SEC 0 // detik idle sebelum tidur (0 = mati; aktifkan 30 setelah tombol bangun terpasang)

// Deteksi daya OPSIONAL:
#define POWER_MAINS_PIN -1 // LOW/HIGH -> nonaktif; gunakan ADC-free gpio (mis. 32)
#define BATTERY_ADC_PIN -1 // pin ADC membaca pembagi tegangan baterai (-1 = mati)

// Ambang analog -> persen (kalibrasi suplai sendiri bila dipakai)
const int BAT_FULL = 3300;  // analog ~3.3V penuh   (>3.3V dibaca penuh)
const int BAT_EMPTY = 2700; // analog ~2.7V kosong

// ---------------------------------------------------------------------------
//  OBJEK
// ---------------------------------------------------------------------------
MFRC522 rFID(RFID_SS_PIN, RFID_RST_PIN);
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
WebServer server(80);
DNSServer dnsServer;
WiFiClientSecure tlsClient;

String serialBuf = "";              // buffer perintah konfigurasi via Serial Monitor
void handleSerialLine(String line); // (didefinisikan di bawah; dipakai portal)

unsigned long lastHeartbeat = 0;
unsigned long lastActive = 0;
unsigned long lastCardSeen = 0;
const unsigned long HEARTBEAT_INTERVAL = 30000;
const unsigned long CARD_DEDUP_MS = 3000;
const unsigned long FLUSH_RETRY = 3000; // jeda ulang kirim buffer saat online
String lastUid = "";
bool clockOk = false;

// ---------------------------------------------------------------------------
//  PENYIMPANAN FLASH (Preferences/NVS)
// ---------------------------------------------------------------------------
Preferences prefs;
String cfgSsid = "";
String cfgPass = "";
String cfgServer = DEFAULT_SERVER;
String cfgApiKey = DEFAULT_APIKEY;

void loadConfig()
{
  prefs.begin("absensi", false);
  cfgSsid = prefs.getString("ssid", "");
  cfgPass = prefs.getString("pass", "");
  cfgServer = prefs.getString("server", DEFAULT_SERVER);
  cfgApiKey = prefs.getString("apikey", DEFAULT_APIKEY);
  prefs.end();
  if (cfgSsid.length() == 0)
    Serial.println(F("[Setup] Belum ada konfigurasi WiFi tersimpan."));
  else
  {
    Serial.print(F("[Setup] SSID tersimpan: "));
    Serial.println(cfgSsid);
    Serial.print(F("[Setup] Server: "));
    Serial.println(cfgServer);
  }
}

void saveConfig(const String &ssid, const String &pass, const String &server, const String &apikey)
{
  prefs.begin("absensi", false);
  prefs.putString("ssid", ssid);
  prefs.putString("pass", pass);
  prefs.putString("server", server.length() ? server : cfgServer);
  prefs.putString("apikey", apikey.length() ? apikey : cfgApiKey);
  prefs.end();
}

// ---------------------------------------------------------------------------
//  JAM (NTP) â€” hanya untuk LCD & timestamp buffer offline
// ---------------------------------------------------------------------------
String nowIso()
{
  struct tm t;
  if (!getLocalTime(&t))
    return "";
  char buf[24];
  snprintf(buf, sizeof(buf), "%04d-%02d-%02dT%02d:%02d:%02d",
           t.tm_year + 1900, t.tm_mon + 1, t.tm_mday, t.tm_hour, t.tm_min, t.tm_sec);
  return String(buf);
}

String nowClock()
{
  struct tm t;
  if (!getLocalTime(&t))
    return "--:--:--";
  char buf[12];
  snprintf(buf, sizeof(buf), "%02d:%02d:%02d", t.tm_hour, t.tm_min, t.tm_sec);
  return String(buf);
}

String nowDate()
{
  struct tm t;

  if (!getLocalTime(&t))
    return "--/--/--";

  char buf[12];

  snprintf(buf, sizeof(buf), "%02d/%02d/%02d",
           t.tm_mday,
           t.tm_mon + 1,
           (t.tm_year + 1900) % 100);

  return String(buf);
}

void syncNtp()
{
  configTime(NTP_TZ_OFFSET, NTP_DAYLIGHT, NTP_SERVER1, NTP_SERVER2);
  struct tm t;
  for (int i = 0; i < 10 && !getLocalTime(&t); i++)
  {
    delay(500);
  }
  clockOk = getLocalTime(&t);
  Serial.print(F("[NTP] "));
  Serial.println(clockOk ? nowIso() : F("gagal (buffer offline pakai jam boot)"));
}

// ---------------------------------------------------------------------------
// LCD 16x2 - TANPA KEDIP
// ---------------------------------------------------------------------------

String lcdLastLine[2] = {"", ""};

unsigned long lcdLastClockUpdate = 0;
unsigned long lcdMessageUntil = 0;

bool lcdShowingMessage = false;

// ---------------------------------------------------------------------------
// Pastikan teks maksimal 16 karakter
// ---------------------------------------------------------------------------
String fitLcd(const String &text)
{
  String s = text;

  if (s.length() > LCD_COLS)
  {
    s = s.substring(0, LCD_COLS - 1);
    s += ".";
  }

  while (s.length() < LCD_COLS)
    s += " ";

  return s;
}

// ---------------------------------------------------------------------------
// Tulis baris LCD hanya jika isinya berubah
// ---------------------------------------------------------------------------
void lcdSetLine(int row, const String &text)
{
  if (row < 0 || row >= LCD_ROWS)
    return;

  String s = fitLcd(text);

  // Tidak menulis ulang jika sama.
  // Ini mencegah LCD berkedip.
  if (lcdLastLine[row] == s)
    return;

  lcd.setCursor(0, row);
  lcd.print(s);

  lcdLastLine[row] = s;
}

// ---------------------------------------------------------------------------
// Reset cache LCD
// ---------------------------------------------------------------------------
void lcdResetCache()
{
  lcdLastLine[0] = "";
  lcdLastLine[1] = "";
}

// ---------------------------------------------------------------------------
// Tampilkan pesan
// ---------------------------------------------------------------------------
void lcdShowMessage(const String &line1, const String &line2)
{
  lcdSetLine(0, line1);
  lcdSetLine(1, line2);

  lcdShowingMessage = true;
  lcdMessageUntil = millis() + 4000;
}

// ---------------------------------------------------------------------------
// Tampilan awal
// ---------------------------------------------------------------------------
void lcdShowWelcome()
{
  lcdShowingMessage = false;

  lcdSetLine(0, "SILAKAN TAP");
  lcdSetLine(1, "KARTU RFID...");
}

// ---------------------------------------------------------------------------
// Tampilan jam
// ---------------------------------------------------------------------------
void lcdShowClock()
{
  unsigned long now = millis();

  // Update maksimal 1x per detik
  if (now - lcdLastClockUpdate < 1000)
    return;

  lcdLastClockUpdate = now;

  struct tm t;

  if (!getLocalTime(&t))
  {
    lcdSetLine(0, "--:--:--    TAP");
    lcdSetLine(1, "--/--/--  KARTU");
    return;
  }

  char top[17];
  char bottom[17];

  snprintf(top, sizeof(top),
           "%02d:%02d:%02d   TAP",
           t.tm_hour,
           t.tm_min,
           t.tm_sec);

  snprintf(bottom, sizeof(bottom),
           "%02d/%02d/%02d  KARTU",
           t.tm_mday,
           t.tm_mon + 1,
           (t.tm_year + 1900) % 100);

  lcdSetLine(0, top);
  lcdSetLine(1, bottom);
}

// RTC
//  #include <RTClib.h>

// RTC_DS3231 rtc;

// bool getSystemTime(struct tm &t)
// {
//   // Nanti RTC menjadi sumber utama
//   if (rtcValid)
//   {
//     DateTime now = rtc.now();

//     t.tm_year = now.year() - 1900;
//     t.tm_mon  = now.month() - 1;
//     t.tm_mday = now.day();
//     t.tm_hour = now.hour();
//     t.tm_min  = now.minute();
//     t.tm_sec  = now.second();

//     return true;
//   }

//   // Cadangan sementara: NTP
//   return getLocalTime(&t);
// }

// ---------------------------------------------------------------------------
// Update tampilan LCD
// ---------------------------------------------------------------------------
void lcdUpdate()
{
  if (lcdShowingMessage)
  {
    if (millis() >= lcdMessageUntil)
    {
      lcdShowingMessage = false;
      lcdLastClockUpdate = 0;
    }
  }

  if (!lcdShowingMessage && clockOk)
  {
    lcdShowClock();
  }
}

// ---------------------------------------------------------------------------
//  BUZZER
// ---------------------------------------------------------------------------
void beepPattern(int code)
{
  switch (code)
  {
  case 3: // terlambat: 3 x pendek
    for (int i = 0; i < 3; i++)
    {
      digitalWrite(BUZZER_PIN, HIGH);
      delay(120);
      digitalWrite(BUZZER_PIN, LOW);
      delay(120);
    }
    break;
  case 4: // gagal: 1 x panjang
    digitalWrite(BUZZER_PIN, HIGH);
    delay(500);
    digitalWrite(BUZZER_PIN, LOW);
    break;
  default: // sukses: 1 x pendek
    digitalWrite(BUZZER_PIN, HIGH);
    delay(100);
    digitalWrite(BUZZER_PIN, LOW);
    break;
  }
}

// ---------------------------------------------------------------------------
//  DAYA & BATERAI (opsional)
// ---------------------------------------------------------------------------
String currentPowerSource()
{
#if POWER_MAINS_PIN >= 0
  return digitalRead(POWER_MAINS_PIN) == HIGH ? "MAINS" : "BATTERY";
#else
  return "";
#endif
}

int currentBatteryPercent()
{
#if BATTERY_ADC_PIN >= 0
  int v = 0;
  for (int i = 0; i < 8; i++)
    v += analogRead(BATTERY_ADC_PIN);
  v /= 8;
  int p = map(v, BAT_EMPTY, BAT_FULL, 0, 100);
  return constrain(p, 0, 100);
#else
  return -1;
#endif
}

// ---------------------------------------------------------------------------
//  WiFi
// ---------------------------------------------------------------------------
bool tryConnectWiFi(int timeoutSec)
{
  WiFi.mode(WIFI_STA);
  WiFi.begin(cfgSsid.c_str(), cfgPass.c_str());
  WiFi.setSleep(false);

  unsigned long start = millis();
  Serial.print(F("[WiFi] Menghubungkan ke "));
  Serial.println(cfgSsid);
  while (WiFi.status() != WL_CONNECTED && (millis() - start) < (unsigned long)timeoutSec * 1000)
  {
    delay(300);
    Serial.print(F("."));
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED)
  {
    Serial.print(F("[WiFi] IP: "));
    Serial.println(WiFi.localIP());
    syncNtp();
    return true;
  }
  Serial.println(F("[WiFi] GAGAL terhubung!"));
  return false;
}

// ---------------------------------------------------------------------------
//  CAPTIVE PORTAL (setup WiFi via HP tanpa laptop)
// ---------------------------------------------------------------------------
const int MAX_SCAN = 16;
String scanList[MAX_SCAN];
int scanCount = 0;

void doWifiScan()
{
  scanCount = 0;
  int n = WiFi.scanNetworks();
  if (n < 0)
    n = 0;
  if (n > MAX_SCAN)
    n = MAX_SCAN;
  for (int i = 0; i < n; i++)
    scanList[scanCount++] = WiFi.SSID(i);
  WiFi.scanDelete();
}

String buildPortalHtml()
{
  String html = F(
      "<!DOCTYPE html><html lang='id'><head><meta charset='utf-8'>"
      "<meta name='viewport' content='width=device-width,initial-scale=1'>"
      "<title>Setup ESP32 Absensi</title><style>"
      "body{font-family:Segoe UI,Arial,sans-serif;background:#0f172a;color:#e2e8f0;"
      "display:flex;justify-content:center;padding:24px}"
      ".box{background:#1e293b;padding:24px;border-radius:12px;width:100%;max-width:420px}"
      "h1{font-size:20px;margin:0 0 4px}sub{color:#94a3b8}"
      "label{display:block;margin:14px 0 4px;font-size:13px;color:#cbd5e1}"
      "input,select{width:100%;padding:10px;border:1px solid #334155;border-radius:8px;"
      "background:#0f172a;color:#e2e8f0;font-size:15px;box-sizing:border-box}"
      "button{margin-top:20px;width:100%;padding:12px;border:0;border-radius:8px;"
      "background:#10b981;color:#fff;font-size:16px;font-weight:600;cursor:pointer}"
      ".ok{color:#10b981;text-align:center;margin-top:16px;font-weight:600}"
      "details{margin-top:16px}summary{color:#94a3b8;font-size:13px;cursor:pointer}"
      "</style></head><body><div class='box'>"
      "<h1>Konfigurasi ESP32 Absensi</h1><sub>Isi WiFi yang dipakai perangkat.</sub>"
      "<form method='POST' action='/save'>"
      "<label>SSID (nama hotspot HP)</label>");

  if (scanCount > 0)
  {
    html += F("<select name='ssid'>");
    for (int i = 0; i < scanCount; i++)
      html += "<option value=\"" + scanList[i] + "\">" + scanList[i] + "</option>";
    html += F("</select>");
  }
  else
  {
    html += F(
        "<input type='text' name='ssid' placeholder='Ketik nama WiFi / hotspot'"
        " style='border-color:#f87171'>"
        "<br><small style='color:#f87171'>Scan kosong. Ketik nama SSID manual,"
        " pastikan hotspot HP aktif (2.4 GHz), lalu muat ulang halaman.</small>");
  }

  html += F(
      "<label>Password WiFi</label><input type='password' name='pass' placeholder=''>"
      "<details><summary>Pengaturan lanjutan (server)</summary>"
      "<label>Alamat Server (isi IP komputer, tanpa /api)</label>"
      "<input type='text' name='server' value='");
  html += cfgServer;
  html += F("'><label>API Key</label><input type='text' name='apikey' value='");
  html += cfgApiKey;
  html += F(
      "'></details>"
      "<button type='submit'>Simpan &amp; Sambungkan</button>"
      "</form></div></body></html>");
  return html;
}

void handlePortalRoot()
{
  server.send(200, "text/html", buildPortalHtml());
}

void handlePortalSave()
{
  String ssid = server.arg("ssid");
  String pass = server.arg("pass");
  String serverUrl = server.arg("server");
  String apikey = server.arg("apikey");
  ssid.trim();

  if (ssid.length() == 0)
  {
    server.send(200, "text/html",
                F("<html><body style='font-family:sans-serif;text-align:center;padding:40px'>"
                  "<h2>SSID tidak boleh kosong</h2><a href='/'>Kembali</a></body></html>"));
    return;
  }

  saveConfig(ssid, pass, serverUrl, apikey);
  Serial.println(F("[Setup] Konfigurasi tersimpan, restart..."));
  server.send(200, "text/html",
              F("<html><body style='font-family:sans-serif;text-align:center;padding:40px'>"
                "<h2 style='color:#10b981'>Tersimpan. Menghubungkan ulang...</h2>"
                "<p>Perangkat akan restart. Jika berhasil, kartu bisa langsung ditap.</p>"
                "</body></html>"));
  beepPattern(1);
  delay(1500);
  ESP.restart();
}

void runProvisioningPortal()
{
  lcdShowMessage("SETUP WIFI:", "Scan jaringan...");

  // Scan SSID harus dilakukan saat mode STA (mode AP murni gak bisa scan)
  WiFi.mode(WIFI_STA);
  doWifiScan();

  WiFi.mode(WIFI_AP);
  WiFi.softAP(AP_NAME, AP_PASS);
  IPAddress apIP = WiFi.softAPIP();
  Serial.print(F("[AP] 192.168.4.1? -> "));
  Serial.println(apIP);
  Serial.print(F("[Scan] SSID ditemukan: "));
  Serial.println(scanCount);

  dnsServer.start(53, "*", apIP);
  server.on("/", HTTP_GET, handlePortalRoot);
  server.on("/save", HTTP_POST, handlePortalSave);
  server.begin();

  beepPattern(4);

  while (true)
  {
    dnsServer.processNextRequest();
    server.handleClient();

    // Terima konfigurasi via Serial juga (biar tetap bisa set tanpa browser)
    while (Serial.available())
    {
      char ch = Serial.read();
      if (ch == '\n' || ch == '\r')
      {
        if (serialBuf.length())
        {
          handleSerialLine(serialBuf);
          serialBuf = "";
        }
      }
      else
        serialBuf += ch;
    }

    lcdShowMessage("SETUP WIFI", "AP: ABSENSI");
    delay(20);
  }
}

// ---------------------------------------------------------------------------
//  HTTP
// ---------------------------------------------------------------------------
// Bangun URL API yang AMAN terhadap kesalahan umum:
//   - base yang diakhiri '/' berlebih
//   - base yang mengandung '/api' berulang (mis. diset '/api/api')
String buildApiUrl(const String &path)
{
  String base = cfgServer;
  base.trim();
  while (base.length() > 7 && base.endsWith("/"))
    base.remove(base.length() - 1);
  while (base.length() > 7 && base.endsWith("/api"))
    base.remove(base.length() - 4);

  String p = path;
  if (!p.startsWith("/api"))
    p = "/api" + p;
  return base + p;
}

bool postJson(const String &path, const String &body, String &responseBody)
{
  String url = buildApiUrl(path);
  HTTPClient http;
  if (url.startsWith("https://"))
  {
    tlsClient.setInsecure(); // tanpa verifikasi CA (hosting tanpa sertifikat khusus)
    http.begin(tlsClient, url);
  }
  else
  {
    http.begin(url);
  }
  http.setTimeout(12000); // HTTPS pertama ke tunnel bisa lambat; hindari false-"offline"
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-KEY", cfgApiKey);

  int code = http.POST(body);
  responseBody = (code > 0) ? http.getString() : "";
  if (code <= 0)
  {
    Serial.print(F("  err = "));
    Serial.println(http.errorToString(code));
    Serial.print(F("  WiFi status = "));
    Serial.println(WiFi.status());
    Serial.print(F("  localIP = "));
    Serial.println(WiFi.localIP());
    Serial.print(F("  DNS = "));
    Serial.println(WiFi.dnsIP());
  }
  http.end();

  Serial.println(F("[HTTP] ----------------------------------------------"));
  Serial.println(url);
  Serial.println(body);
  Serial.print(F("  code = "));
  Serial.println(code);
  Serial.println(responseBody);
  return code == 200;
}

bool sendHeartbeat()
{
  String body;
  StaticJsonDocument<192> doc;
  doc["device_name"] = "ESP32-Absensi-" + WiFi.macAddress().substring(12);
  String ps = currentPowerSource();
  int bp = currentBatteryPercent();
  if (ps.length())
    doc["power_source"] = ps;
  if (bp >= 0)
    doc["battery_percent"] = bp;
  doc["wifi_rssi"] = WiFi.RSSI();
  serializeJson(doc, body);

  String resp;
  bool ok = postJson("/device/heartbeat", body, resp);
  if (ok)
    Serial.println(F("[SYS] heartbeat OK"));
  return ok;
}

// ---------------------------------------------------------------------------
//  BUFFER OFFLINE (LittleFS) â€” antrean tap saat WiFi/server mati
// ---------------------------------------------------------------------------
const char *BUF_FILE = "/buf.json";

// Baca buffer -> doc berisi {"queue":[...]}
bool readBuffer(StaticJsonDocument<4096> &doc)
{
  File f = LittleFS.open(BUF_FILE, "r");
  if (!f)
  {
    doc["queue"].to<JsonArray>();
    return true;
  }
  DeserializationError err = deserializeJson(doc, f);
  f.close();
  if (err)
  {
    doc["queue"].to<JsonArray>();
    return true;
  }
  return true;
}

bool writeBuffer(const StaticJsonDocument<4096> &doc)
{
  File f = LittleFS.open(BUF_FILE, "w");
  if (!f)
    return false;
  serializeJson(doc, f);
  f.close();
  return true;
}

static int pendingCount = 0;

void queueTap(const String &uid, const String &iso)
{
  StaticJsonDocument<4096> doc;
  readBuffer(doc);
  JsonArray q = doc["queue"].to<JsonArray>();

  JsonObject item = q.createNestedObject();
  item["uid"] = uid;
  item["ts"] = iso;
  item["c"] = 0; // percobaan pengiriman

  writeBuffer(doc);
  pendingCount = q.size();
}

// Kirim semua antrean yang tertunda (dipanggil saat WiFi online).
void flushBuffer()
{
  while (WiFi.status() == WL_CONNECTED)
  {
    StaticJsonDocument<4096> doc;
    readBuffer(doc);
    JsonArray q = doc["queue"].to<JsonArray>();
    if (q.size() == 0)
    {
      pendingCount = 0;
      return;
    }

    JsonObject it = q[0];
    String uid = it["uid"].as<String>();
    String ts = it["ts"].as<String>();
    int attempts = it["c"] | 0;

    String body;
    StaticJsonDocument<192> p;
    p["uid"] = uid;
    p["mode"] = "auto";
    p["source"] = "RFID";
    if (ts.length() >= 19)
      p["timestamp"] = ts;
    serializeJson(p, body);

    String resp;
    bool ok = postJson("/attendance", body, resp);

    if (ok)
    {
      q.remove(0); // berhasil -> keluar antrean
      writeBuffer(doc);
      pendingCount = q.size();
      lcdShowMessage("KIRIM BUFFER", "Sisa: " + String(q.size()));
      Serial.println(F("[BUF] satu antrean terkirim"));
      delay(60);
    }
    else
    {
      attempts++;
      if (attempts >= 3)
        it["c"] = 0; // istirahat sebentar, coba lagi nanti
      else
        it["c"] = attempts;
      writeBuffer(doc);
      Serial.println(F("[BUF] gagal kirim, tunggu retry"));
      return; // coba lagi di loop berikutnya (tidak memblokir kartu tap)
    }
  }
}

// ---------------------------------------------------------------------------
// ABSENSI
// ---------------------------------------------------------------------------
void doAttendance(const String &uid, const String &iso)
{
  String body;

  StaticJsonDocument<192> doc;

  doc["uid"] = uid;
  doc["mode"] = "auto";
  doc["source"] = "RFID";

  if (iso.length() >= 19)
    doc["timestamp"] = iso;

  serializeJson(doc, body);

  String resp;

  bool ok = postJson("/attendance", body, resp);

  StaticJsonDocument<512> out;

  // -------------------------------------------------------------------------
  // Server tidak merespon / response bukan JSON
  // -------------------------------------------------------------------------
  if (!ok || deserializeJson(out, resp))
  {
    queueTap(uid, iso);

    if (WiFi.status() == WL_CONNECTED)
    {
      // WiFi nyala tapi server belum merespon: data AMAN, terkirim otomatis
      // beberapa detik kemudian lewat flushBuffer (tidak perlu tap ulang).
      lcdShowMessage(
          "TERANTRI",
          "Dikirim: " + String(pendingCount));

      beepPattern(1);
    }
    else
    {
      lcdShowMessage(
          "WIFI OFFLINE",
          "Antre: " + String(pendingCount));

      beepPattern(4);
    }

    return;
  }

  // -------------------------------------------------------------------------
  // Baca hasil dari server
  // -------------------------------------------------------------------------
  bool success = out["success"] | false;

  if (success)
  {
    const char *name = out["employee"] | "?";
    const char *time = out["time"] | "--:--";

    int beep = out["beep"] | 1;

    // -----------------------------------------------------------------------
    // BARIS 1
    // Nama + jam, maksimal 16 karakter
    // -----------------------------------------------------------------------
    String line1 = String(name);
    String jam = String(time);

    if (jam.length() > 5)
      jam = jam.substring(0, 5);

    int maxNameLen = LCD_COLS - jam.length() - 1;

    if (maxNameLen < 1)
      maxNameLen = 1;

    if (line1.length() > maxNameLen)
      line1 = line1.substring(0, maxNameLen);

    while (line1.length() < maxNameLen)
      line1 += " ";

    line1 += " ";
    line1 += jam;

    // -----------------------------------------------------------------------
    // BARIS 2
    // Status
    // -----------------------------------------------------------------------
    String line2 = out["status"] | "";

    int late = out["late_minutes"] | 0;

    if (beep == 3 && late > 0)
    {
      String lateText = "+" + String(late) + "mnt";

      if (line2.length() + lateText.length() + 1 <= LCD_COLS)
      {
        line2 += " ";
        line2 += lateText;
      }
    }

    lcdShowMessage(line1, line2);

    beepPattern(beep);
  }
  else
  {
    // -----------------------------------------------------------------------
    // Absensi ditolak
    // -----------------------------------------------------------------------
    const char *msg = out["message"] | "DITOLAK";

    lcdShowMessage(
        String(msg),
        "DAFTARKAN DI WEB");

    beepPattern(4);
  }
}

// ---------------------------------------------------------------------------
//  I2C SCANNER (diagnosa LCD/backpack)
// ---------------------------------------------------------------------------
// Tampilkan semua alamat perangkat di bus I2C via Serial Monitor (115200).
// - "Ditemukan 0x3F" -> ganti define LCD_ADDR dari 0x27 menjadi 0x3F.
// - Tidak ada yang terdeteksi -> cek daya (5V), kontras, dan kabel SDA/SCL.
void scanI2C()
{
  byte error;
  byte address;
  int n = 0;
  Serial.println(F("[I2C] Scanning bus..."));
  for (address = 1; address < 127; address++)
  {
    Wire.beginTransmission(address);
    error = Wire.endTransmission();
    if (error == 0)
    {
      Serial.print(F("[I2C] Ditemukan perangkat di 0x"));
      if (address < 16)
        Serial.print('0');
      Serial.println(address, HEX);
      n++;
    }
  }
  Serial.print(F("[I2C] Selesai. "));
  Serial.print(n);
  Serial.println(F(" perangkat terhubung."));
}

// ---------------------------------------------------------------------------
//  DEEP SLEEP (hemat daya saat tidak dipakai)
// ---------------------------------------------------------------------------
void goSleep()
{
#if WAKE_PIN >= 0
  Serial.println(F("[SLP] idle -> deep sleep. Tekan tombol untuk bangun."));
  lcd.noBacklight();
  lcd.clear();
  pinMode(WAKE_PIN, INPUT_PULLUP);
  esp_sleep_enable_ext0_wakeup((gpio_num_t)WAKE_PIN, LOW);
  esp_deep_sleep_start();
#else
  Serial.println(F("[SLP] WAKE_PIN mati, sleep dinonaktifkan."));
#endif
}

// ---------------------------------------------------------------------------
//  SETUP
// ---------------------------------------------------------------------------
void setup()
{
  Serial.begin(115200);
  delay(200);

  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

#if POWER_MAINS_PIN >= 0
  pinMode(POWER_MAINS_PIN, INPUT_PULLDOWN);
#endif
#if BATTERY_ADC_PIN >= 0
  pinMode(BATTERY_ADC_PIN, INPUT);
  analogSetPinAttenuation(BATTERY_ADC_PIN, ADC_11db);
#endif

  Wire.begin(LCD_SDA, LCD_SCL);
  scanI2C();
  lcd.init();
  lcd.backlight();

  lcdResetCache();
  lcdShowMessage("ABSENSI RFID", "MENGHUBUNGKAN");

  if (!LittleFS.begin(true))
    Serial.println(F("[FS] LittleFS mount gagal!"));

  loadConfig();

  if (CONFIG_PIN >= 0)
  {
    pinMode(CONFIG_PIN, INPUT_PULLUP);
    if (digitalRead(CONFIG_PIN) == LOW)
      runProvisioningPortal();
  }

  if (cfgSsid.length() == 0)
  {
    Serial.println(F("[Setup] Masuk portal (SSID kosong)."));
    runProvisioningPortal();
  }

  if (!tryConnectWiFi(15))
  {
    Serial.println(F("[Setup] Masuk portal (WiFi gagal konek)."));
    runProvisioningPortal();
  }

  SPI.begin();
  rFID.PCD_Init();
  Serial.print(F("[RFID] Versi firmware: 0x"));
  Serial.println(rFID.PCD_ReadRegister(MFRC522::VersionReg), HEX);

  lastActive = millis();
  flushBuffer(); // kalau ada antrean dari offline/sleep sebelumnya
  sendHeartbeat(); // warm-up DNS+TLS supaya tap pertama tidak "timeout"
  lcdShowWelcome();
}

// ---------------------------------------------------------------------------
//  LOOP
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  KONFIGURASI VIA SERIAL MONITOR (tanpa perlu join AP / browser)
//  Contoh:
//    ssid    Redmi Note 8        -> nama WiFi/hotspot
//    pass                        -> kosongkan sandi (hotspot "Open")
//    server  http://192.168.42.115:8010
//    apikey  penggajian-rfid-demo-2026
//    save                        -> simpan ke flash + restart
//    set / status                -> tampilkan nilai saat ini
//    clear                       -> hapus konfigurasi + masuk portal
//    help                        -> daftar perintah
// ---------------------------------------------------------------------------
void handleSerialLine(String line)
{
  line.trim();
  if (line.length() == 0)
    return;

  String cmd = line;
  String arg = "";
  int sp = line.indexOf(' ');
  if (sp > 0)
  {
    cmd = line.substring(0, sp);
    arg = line.substring(sp + 1);
    arg.trim();
  }

  String cmdKey = cmd;
  cmdKey.toLowerCase();

  if (line == "help" || cmdKey == "help")
  {
    Serial.println(F("Perintah: ssid <nama>, pass <sandi>, server <url>, apikey <key>, save, set, status, clear, r"));
    return;
  }

  if (cmdKey == "r" || cmdKey == "clear")
  {
    Serial.println(F("[Setup] Menghapus konfigurasi & masuk portal..."));
    prefs.begin("absensi", false);
    prefs.clear();
    prefs.end();
    ESP.restart();
    return;
  }

  if (cmdKey == "set" || cmdKey == "status")
  {
    Serial.print(F("SSID: "));
    Serial.println(cfgSsid.length() ? cfgSsid : "(kosong)");
    Serial.print(F("Server: "));
    Serial.println(cfgServer);
    Serial.print(F("API Key: "));
    Serial.println(cfgApiKey);
    Serial.print(F("Buffer offline: "));
    Serial.println(pendingCount);
    return;
  }

  if (cmdKey == "ssid" && arg.length())
  {
    cfgSsid = arg;
    Serial.print(F("SSID diset: "));
    Serial.println(cfgSsid);
    return;
  }
  if (cmdKey == "pass")
  {
    cfgPass = arg; // kosong = jaringan open
    Serial.println(F("Password diset (kosong jika open)."));
    return;
  }
  if (cmdKey == "server" && arg.length())
  {
    cfgServer = arg;
    Serial.print(F("Server diset: "));
    Serial.println(cfgServer);
    return;
  }
  if (cmdKey == "apikey" && arg.length())
  {
    cfgApiKey = arg;
    Serial.print(F("API Key diset: "));
    Serial.println(cfgApiKey);
    return;
  }
  if (cmdKey == "save")
  {
    if (cfgSsid.length() == 0)
      Serial.println(F("SSID masih kosong. Ketik: ssid <nama>"));
    else
    {
      saveConfig(cfgSsid, cfgPass, cfgServer, cfgApiKey);
      Serial.println(F("[Setup] Tersimpan. Restart untuk menyambung..."));
      delay(400);
      ESP.restart();
    }
    return;
  }

  Serial.println(F("Perintah tidak dikenal. Ketik: help"));
}

void loop()
{
  unsigned long now = millis();

  // Konfigurasi via Serial Monitor (mode tanpa browser/AP)
  while (Serial.available())
  {
    char ch = Serial.read();
    if (ch == '\n' || ch == '\r')
    {
      if (serialBuf.length())
      {
        handleSerialLine(serialBuf);
        serialBuf = "";
      }
    }
    else
      serialBuf += ch;
  }

  // Heartbeat berkala + isi ulang jam NTP bila belum kedapatan
  if (now - lastHeartbeat >= HEARTBEAT_INTERVAL)
  {
    lastHeartbeat = now;
    if (WiFi.status() == WL_CONNECTED)
    {
      sendHeartbeat();
      if (!clockOk)
        syncNtp();
      else if (pendingCount > 0)
      {
        flushBuffer();
        if (pendingCount == 0)
          lcdShowWelcome();
      }
    }
  }

  // Tampilkan jam di LCD saat santai (update tiap ~1 dtk via heartbeat)
  // Update LCD secara non-blocking.
  // Tidak menggunakan lcd.clear() setiap detik.
  lcdUpdate();

  if (!lcdShowingMessage && clockOk)
  {
    lcdShowClock();
  }

  // Deep sleep bila benar-benar idle (tanpa buffer tersisa, tanpa kartu)
#if WAKE_PIN >= 0
  if (SELIDLE_SEC > 0 && pendingCount == 0 && (now - lastActive) > (unsigned long)SELIDLE_SEC * 1000)
    goSleep();
#endif

  if (!rFID.PICC_IsNewCardPresent() || !rFID.PICC_ReadCardSerial())
    return;

  String uid = "";
  for (byte i = 0; i < rFID.uid.size; i++)
  {
    if (rFID.uid.uidByte[i] < 0x10)
      uid += "0";
    uid += String(rFID.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  rFID.PICC_HaltA();

  if (uid == lastUid && (now - lastCardSeen) < CARD_DEDUP_MS)
    return;
  lastUid = uid;
  lastCardSeen = now;
  lastActive = now;

  Serial.print(F("[RFID] UID = "));
  Serial.println(uid);

  if (WiFi.status() == WL_CONNECTED)
    doAttendance(uid, nowIso());
  else
  {
    queueTap(uid, nowIso());
    lcdShowMessage("WIFI OFFLINE", "Antre: " + String(pendingCount));
    beepPattern(1);
  }
}