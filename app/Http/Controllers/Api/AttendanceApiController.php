<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\RfidUnknown;
use App\Services\AttendanceService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    public function __construct(
        private AttendanceService $service,
        private NotificationService $notifier,
    ) {}

    /**
     * Menerima absensi dari ESP32.
     *
     * Body:
     *   uid          : string (UID RFID, misal "A1B2C3D4")
     *   employee_id  : string (alternatif, ID pegawai misal "PGW001")
     *   mode         : masuk | pulang | izin | auto (default: auto)
     *                  auto -> pertama = masuk, kedua = pulang
     *   source       : RFID | KEYPAD (default RFID)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uid' => ['nullable', 'string', 'max:32'],
            'employee_id' => ['nullable', 'string', 'max:20'],
            'mode' => ['nullable', 'in:masuk,pulang,izin,auto'],
            'source' => ['nullable', 'in:RFID,KEYPAD,ADMIN'],
            'timestamp' => ['nullable', 'date'],
        ]);

        if (empty($data['uid']) && empty($data['employee_id'])) {
            return $this->fail('uid atau employee_id wajib diisi.');
        }

        $source = strtoupper($data['source'] ?? 'RFID');
        $employee = $this->resolveEmployee($data);

        if (! $employee) {
            $this->recordUnknownCard($request);
            $this->alertUnknownCard($request);
            if (! empty($data['uid'])) {
                return $this->fail('RFID tidak terdaftar.');
            }

            return $this->fail('ID pegawai tidak ditemukan.');
        }

        if (! $employee->isActive()) {
            return $this->fail('Pegawai berstatus nonaktif.');
        }

        $now = $this->resolveTapTime($data['timestamp'] ?? null);
        if (! $now) {
            return $this->fail('Timestamp perangkat tidak valid.');
        }

        $mode = strtolower($data['mode'] ?? 'auto');

        try {
            if ($mode === 'izin') {
                $attendance = $this->service->takeLeave($employee, $now, $source);
                $this->notify('izin', $employee, $attendance, $now);

                return $this->ok('IZIN', $employee, $attendance, 'Status izin tercatat.');
            }

            if ($mode === 'masuk' || ($mode === 'auto' && ! $this->hasTimeIn($employee, $now))) {
                if (! $this->service->scheduleFor($employee, $now)['work_day']) {
                    return $this->fail('Bukan hari kerja.', $employee);
                }

                $attendance = $this->service->checkIn($employee, $now, $source);
                $event = $attendance->status === Attendance::TERLAMBAT ? 'terlambat' : 'hadir';
                $this->notify($event, $employee, $attendance, $now);

                return $this->ok($attendance->status, $employee, $attendance,
                    $attendance->status === Attendance::TERLAMBAT
                        ? 'Terlambat '.$attendance->late_minutes.' menit.'
                        : 'Absensi masuk berhasil.',
                    $attendance->status === Attendance::TERLAMBAT
                );
            }

            if ($mode === 'pulang' || $mode === 'auto') {
                $attendance = $this->service->checkOut($employee, $now, $source);
                $this->notify('pulang', $employee, $attendance, $now);

                return $this->ok('PULANG', $employee, $attendance, 'Absensi pulang berhasil.');
            }

            return $this->fail('Mode tidak dikenali.');
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), $employee);
        }
    }

    /**
     * Endpoint untuk menampilkan nama pegawai berdasarkan UID/ID (untuk LCD).
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uid' => ['nullable', 'string', 'max:32'],
            'employee_id' => ['nullable', 'string', 'max:20'],
        ]);

        $employee = $this->resolveEmployee($data);

        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan.']);
        }

        return response()->json([
            'success' => true,
            'employee' => $employee->name,
            'employee_id' => $employee->employee_id,
            'position' => $employee->position,
        ]);
    }

    private function resolveEmployee(array $data): ?Employee
    {
        if (! empty($data['uid'])) {
            $card = RfidCard::with('employee')
                ->where('uid', strtoupper($data['uid']))
                ->where('is_active', true)
                ->first();

            return $card?->employee;
        }

        return Employee::where('employee_id', strtoupper(trim($data['employee_id'])))
            ->where('status', 'Aktif')
            ->first();
    }

    private function hasTimeIn(Employee $employee, Carbon $now): bool
    {
        return Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $now->toDateString())
            ->whereNotNull('time_in')
            ->exists();
    }

    /**
     * Waktu tap efektif. Bila `timestamp` dikirim (buffer offline ESP32),
     * pakai itu selama masih masuk akal (maks. 3 hari lalu, bukan masa depan).
     */
    private function resolveTapTime(?string $timestamp): ?Carbon
    {
        if (blank($timestamp)) {
            return now();
        }

        try {
            $time = Carbon::parse($timestamp);
        } catch (\Throwable) {
            return null;
        }

        if ($time->gt(now()->addMinutes(2)) || $time->lt(now()->subDays(3))) {
            return null;
        }

        return $time;
    }

    private function notify(string $event, Employee $employee, Attendance $attendance, Carbon $now): void
    {
        $payload = [
            'time' => $attendance->time_in ?? $attendance->time_out,
            'late_minutes' => $attendance->late_minutes,
            'date' => $attendance->attendance_date->toDateString(),
        ];

        $this->notifier->attendanceStatus($employee, $event, $payload);
    }

    private function recordUnknownCard(Request $request): void
    {
        $uid = strtoupper(trim((string) $request->input('uid')));
        if ($uid === '') {
            return;
        }

        $now = now();
        $row = RfidUnknown::where('uid', $uid)->first();

        if ($row) {
            $row->increment('hits');
            $row->update(['last_seen_at' => $now, 'ip_address' => $request->ip()]);

            return;
        }

        RfidUnknown::create([
            'uid' => $uid,
            'ip_address' => $request->ip(),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'hits' => 1,
        ]);
    }

    private function alertUnknownCard(Request $request): void
    {
        $key = 'unknown_rfid_'.today()->toDateString();

        if (! cache()->has($key)) {
            cache()->put($key, true, now()->endOfDay());
            $this->notifier->alertAdmin(
                'Percobaan Absensi dengan Kartu Tidak Terdaftar',
                'Kartu RFID tidak dikenal mencoba melakukan absensi.',
                [
                    'uid' => $request->input('uid'),
                    'ip' => $request->ip(),
                    'waktu' => now()->format('d-m-Y H:i:s'),
                ],
            );
        }
    }

    private function ok(string $status, Employee $employee, Attendance $attendance, string $message, bool $late = false): JsonResponse
    {
        return response()->json([
            'success' => true,
            'employee' => $employee->name,
            'employee_id' => $employee->employee_id,
            'status' => $status,
            'time' => $attendance->time_in ?? $attendance->time_out,
            'late_minutes' => $attendance->late_minutes,
            'message' => $message,
            'beep' => $late ? 3 : 1,
        ]);
    }

    private function fail(string $message, ?Employee $employee = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'employee' => $employee?->name,
            'beep' => 4,
        ], 422);
    }
}
