<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DeviceLog;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\RfidUnknown;
use App\Models\Shift;
use App\Models\WorkDateOverride;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private string $apiKey = 'test-api-key';

    protected function setUp(): void
    {
        Carbon::setTestNow('2026-09-07 07:50:00');
        parent::setUp();
        cache()->flush();
        config(['app.api_key' => $this->apiKey]);
        config(['mail.to_admin' => 'admin@test.local']);
    }

    private function postAttendance(array $payload, bool $withKey = true): TestResponse
    {
        $headers = $withKey ? ['X-API-KEY' => $this->apiKey] : [];

        return $this->postJson('/api/attendance', $payload, $headers);
    }

    public function test_api_requires_api_key(): void
    {
        $this->postJson('/api/attendance', ['uid' => 'A1B2C3D4'])->assertUnauthorized();
        $this->postJson('/api/attendance', ['uid' => 'A1B2C3D4'], ['X-API-KEY' => 'wrong-key'])->assertUnauthorized();
    }

    public function test_unknown_uid_rejected_with_beep_4_and_admin_alert(): void
    {
        $response = $this->postAttendance(['uid' => 'ZZ99', 'mode' => 'auto']);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('beep', 4)
            ->assertJsonPath('message', 'RFID tidak terdaftar.');

        $this->assertDatabaseHas('email_logs', [
            'type' => 'admin-alert',
            'recipient' => 'admin@test.local',
        ]);
    }

    public function test_auto_mode_first_tap_is_checkin(): void
    {
        $response = $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'auto', 'source' => 'RFID']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', Attendance::HADIR)
            ->assertJsonPath('beep', 1)
            ->assertJsonPath('employee', 'John Doe');

        $this->assertDatabaseHas('attendance', [
            'employee_id' => 1,
            'attendance_date' => '2026-09-07',
            'status' => Attendance::HADIR,
        ]);
    }

    public function test_auto_mode_second_tap_is_checkout(): void
    {
        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'auto'])->assertOk();
        Carbon::setTestNow('2026-09-07 17:00:00');

        $response = $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'auto']);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'PULANG')
            ->assertJsonPath('beep', 1);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => 1,
            'attendance_date' => '2026-09-07',
            'time_out' => '17:00:00',
        ]);
    }

    public function test_third_tap_rejected(): void
    {
        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'auto'])->assertOk();
        Carbon::setTestNow('2026-09-07 17:00:00');
        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'auto'])->assertOk();
        Carbon::setTestNow('2026-09-07 17:30:00');

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'auto'])
            ->assertStatus(422)
            ->assertJsonPath('beep', 4);
    }

    public function test_late_checkin_returns_beep_3_and_terlambat(): void
    {
        Carbon::setTestNow('2026-09-07 08:30:00');

        $response = $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk']);

        $response
            ->assertOk()
            ->assertJsonPath('status', Attendance::TERLAMBAT)
            ->assertJsonPath('beep', 3)
            ->assertJsonPath('late_minutes', 15);
    }

    public function test_non_workday_checkin_rejected(): void
    {
        Carbon::setTestNow('2026-09-06 08:00:00');

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bukan hari kerja.');
    }

    public function test_izin_mode_records_izin(): void
    {
        $response = $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'izin']);

        $response->assertOk()->assertJsonPath('status', Attendance::IZIN);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => 1,
            'attendance_date' => '2026-09-07',
            'status' => Attendance::IZIN,
        ]);
    }

    public function test_inactive_employee_rejected(): void
    {
        Employee::where('id', 1)->update(['status' => 'Nonaktif']);

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pegawai berstatus nonaktif.');
    }

    public function test_inactive_card_rejected(): void
    {
        RfidCard::where('uid', 'A1B2C3D4')->update(['is_active' => false]);

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'RFID tidak terdaftar.');
    }

    public function test_lookup_returns_employee(): void
    {
        $this->getJson('/api/employee/lookup?uid=A1B2C3D4', ['X-API-KEY' => $this->apiKey])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('employee', 'John Doe');
    }

    public function test_heartbeat_creates_or_updates_device_log(): void
    {
        $response = $this->postJson('/api/device/heartbeat', [
            'device_name' => 'ESP32-2',
            'ip_address' => '192.168.1.99',
        ], ['X-API-KEY' => $this->apiKey]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, DeviceLog::count());
        $this->assertDatabaseHas('device_logs', [
            'device_name' => 'ESP32-2',
            'ip_address' => '192.168.1.99',
            'online' => true,
        ]);
    }

    public function test_device_status_returns_online(): void
    {
        $this->getJson('/api/device/status', ['X-API-KEY' => $this->apiKey])
            ->assertOk()
            ->assertJsonPath('online', true);
    }

    public function test_duplicate_uid_and_employee_id_require_at_least_one(): void
    {
        $this->postAttendance([])
            ->assertStatus(422);
    }

    public function test_unknown_uid_recorded_as_pending_for_web_registration(): void
    {
        $this->postAttendance(['uid' => 'ZZ99', 'mode' => 'auto'])->assertStatus(422);

        $this->assertDatabaseHas('rfid_unknowns', [
            'uid' => 'ZZ99',
            'hits' => 1,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_unknown_uid_hits_increment_on_repeat_taps(): void
    {
        $this->postAttendance(['uid' => 'ZZ99', 'mode' => 'auto'])->assertStatus(422);
        $this->postAttendance(['uid' => 'ZZ99', 'mode' => 'auto'])->assertStatus(422);

        $this->assertSame(1, RfidUnknown::where('uid', 'ZZ99')->count());
        $this->assertDatabaseHas('rfid_unknowns', ['uid' => 'ZZ99', 'hits' => 2]);
    }

    public function test_unknown_employee_id_does_not_create_pending_row(): void
    {
        $this->postAttendance(['employee_id' => 'PGW999', 'mode' => 'auto'])
            ->assertStatus(422);

        $this->assertSame(0, RfidUnknown::count());
    }

    public function test_shift_employee_follows_shift_grace_and_start_time(): void
    {
        $shift = Shift::create([
            'name' => 'Pagi',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 30,
        ]);
        Employee::where('id', 1)->update(['shift_id' => $shift->id]);

        Carbon::setTestNow('2026-09-07 09:20:00');

        $response = $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk']);

        $response
            ->assertOk()
            ->assertJsonPath('status', Attendance::HADIR)
            ->assertJsonPath('late_minutes', null);
    }

    public function test_employee_without_shift_uses_global_hours(): void
    {
        Carbon::setTestNow('2026-09-07 08:16:00');

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk'])
            ->assertOk()
            ->assertJsonPath('status', Attendance::TERLAMBAT)
            ->assertJsonPath('late_minutes', 1);
    }

    public function test_date_override_turns_holiday_into_workday(): void
    {
        WorkDateOverride::create([
            'work_date' => '2026-09-06',
            'is_work_day' => true,
            'start_time' => '09:00',
            'end_time' => '14:00',
            'label' => 'Kerja lembur Sabtu',
        ]);

        Carbon::setTestNow('2026-09-06 09:05:00');

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk'])
            ->assertOk()
            ->assertJsonPath('status', Attendance::HADIR);
    }

    public function test_date_override_marks_workday_as_holiday(): void
    {
        WorkDateOverride::create([
            'work_date' => '2026-09-07',
            'is_work_day' => false,
            'label' => 'Libur nasional',
        ]);

        $this->postAttendance(['uid' => 'A1B2C3D4', 'mode' => 'masuk'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bukan hari kerja.');
    }

    public function test_device_timestamp_overrides_server_time(): void
    {
        $response = $this->postAttendance([
            'uid' => 'A1B2C3D4',
            'mode' => 'masuk',
            'timestamp' => '2026-09-07 07:45:00',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance', [
            'employee_id' => 1,
            'attendance_date' => '2026-09-07',
            'time_in' => '07:45:00',
            'status' => Attendance::HADIR,
        ]);
    }

    public function test_future_timestamp_rejected(): void
    {
        $this->postAttendance([
            'uid' => 'A1B2C3D4',
            'mode' => 'masuk',
            'timestamp' => '2026-09-10 08:00:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Timestamp perangkat tidak valid.');
    }

    public function test_heartbeat_stores_battery_and_signal(): void
    {
        $this->postJson('/api/device/heartbeat', [
            'device_name' => 'ESP32-2',
            'power_source' => 'BATTERY',
            'battery_percent' => 45,
            'wifi_rssi' => -67,
        ], ['X-API-KEY' => $this->apiKey])->assertOk();

        $this->assertDatabaseHas('device_logs', [
            'device_name' => 'ESP32-2',
            'power_source' => 'BATTERY',
            'battery_percent' => 45,
            'wifi_rssi' => -67,
        ]);
    }
}
