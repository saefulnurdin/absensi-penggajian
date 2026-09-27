<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\WorkDateOverride;
use App\Models\WorkDay;
use Carbon\Carbon;

class AttendanceService
{
    public const SOURCE_RFID = 'RFID';

    public const SOURCE_ADMIN = 'ADMIN';

    /**
     * Jadwal efektif pegawai pada satu tanggal.
     * Prioritas: tanggal khusus (override) > shift pegawai > jam global.
     */
    public function scheduleFor(Employee $employee, Carbon $date): array
    {
        $dateString = $date->toDateString();
        $globalStart = Setting::workStart();
        $globalEnd = Setting::workEnd();
        $globalTolerance = Setting::lateTolerance();

        $override = WorkDateOverride::where('work_date', $dateString)->first();

        if ($override) {
            return [
                'work_day' => (bool) $override->is_work_day,
                'start_time' => $override->start_time ?? $globalStart,
                'end_time' => $override->end_time ?? $globalEnd,
                'tolerance' => $globalTolerance,
            ];
        }

        $shift = $employee->shift;

        return [
            'work_day' => Setting::isWorkDay($date),
            'start_time' => $shift?->start_time ?? $globalStart,
            'end_time' => $shift?->end_time ?? $globalEnd,
            'tolerance' => $shift?->grace_minutes ?? $globalTolerance,
        ];
    }

    /**
     * Alur absensi masuk (Hadir / Terlambat).
     */
    public function checkIn(Employee $employee, Carbon $timestamp, string $source, ?string $editedBy = null): Attendance
    {
        $date = $timestamp->toDateString();
        $time = $timestamp->format('H:i:s');

        $existing = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $date)
            ->first();

        // Validasi anti duplikasi absen masuk.
        if ($existing && $existing->time_in !== null) {
            throw new \DomainException('Anda sudah melakukan absensi masuk hari ini.');
        }

        if ($existing && $existing->status === Attendance::IZIN) {
            throw new \DomainException('Status IZIN sudah tercatat untuk hari ini.');
        }

        $schedule = $this->scheduleFor($employee, $timestamp);
        $lateMinutes = $this->minutesLate($time, $schedule['start_time'], $schedule['tolerance'], $schedule['end_time']);
        $status = $lateMinutes > 0 ? Attendance::TERLAMBAT : Attendance::HADIR;

        $data = [
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'time_in' => $time,
            'status' => $status,
            'late_minutes' => $lateMinutes > 0 ? $lateMinutes : null,
            'source' => $source,
            'edited_by' => $editedBy,
        ];

        return $existing
            ? tap($existing)->update($data + ['edit_reason' => $existing->edit_reason])
            : Attendance::create($data);
    }

    /**
     * Alur absensi pulang.
     */
    public function checkOut(Employee $employee, Carbon $timestamp, string $source, ?string $editedBy = null): Attendance
    {
        $date = $timestamp->toDateString();
        $time = $timestamp->format('H:i:s');

        $existing = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $date)
            ->first();

        if (! $existing || $existing->time_in === null) {
            throw new \DomainException('Absensi masuk tidak ditemukan untuk hari ini.');
        }

        if ($existing->time_out !== null) {
            throw new \DomainException('Absensi pulang sudah tercatat.');
        }

        $existing->update([
            'time_out' => $time,
            'status' => $existing->status === Attendance::TERLAMBAT ? Attendance::TERLAMBAT : Attendance::HADIR,
            'source' => $source,
            'edited_by' => $editedBy,
        ]);

        return $existing;
    }

    /**
     * Alur absensi izin.
     */
    public function takeLeave(Employee $employee, Carbon $timestamp, string $source, ?string $note = null, ?string $editedBy = null): Attendance
    {
        $date = $timestamp->toDateString();

        $existing = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $date)
            ->first();

        if ($existing && $existing->status === Attendance::IZIN) {
            throw new \DomainException('Status IZIN sudah tercatat untuk tanggal tersebut.');
        }

        if ($existing && $existing->time_in !== null) {
            throw new \DomainException('Pegawai sudah melakukan absensi hadir pada tanggal tersebut.');
        }

        $data = [
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => Attendance::IZIN,
            'source' => $source,
            'note' => $note,
            'edited_by' => $editedBy,
        ];

        return $existing
            ? tap($existing)->update($data)
            : Attendance::create($data);
    }

    /**
     * Menghitung menit keterlambatan berdasarkan toleransi.
     * 08:00 + toleransi 15 menit -> batas 08:15. Tap 08:12 -> 0 menit terlambat (HADIR).
     * Shift lintas malam (start > end, mis. 23:00-07:00): tap di pagi hari
     * (sebelum jam selesai) diukur terhadap batas dari hari sebelumnya.
     */
    public function minutesLate(string $time, string $workStart, int $toleranceMinutes, ?string $workEnd = null): int
    {
        $actual = Carbon::parse($time);
        $limit = Carbon::parse($workStart)->addMinutes($toleranceMinutes);

        if ($workEnd !== null && $workStart > $workEnd && $actual->format('H:i:s') < $workEnd) {
            $limit->subDay();
        }

        $late = (int) $limit->diffInMinutes($actual, false);

        return $late > 0 ? $late : 0;
    }

    /**
     * Daftar tanggal hari kerja dalam satu bulan.
     */
    public function workDates(int $year, int $month): array
    {
        $days = WorkDay::where('is_work_day', true)->pluck('day_index')->all();
        if (empty($days)) {
            $days = [1, 2, 3, 4, 5];
        }

        $dates = [];
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if (in_array((int) $day->format('w'), $days, true)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    /**
     * Menentukan kehadiran pegawai pada satu tanggal.
     * Jika bukan hari kerja -> null (tidak dihitung).
     */
    public function determineDailyStatus(Employee $employee, Carbon $date): ?array
    {
        if (! $this->scheduleFor($employee, $date)['work_day']) {
            return null;
        }

        $row = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $date->toDateString())
            ->first();

        if (! $row) {
            return null; // belum ada catatan -> perlu dihitung sebagai TIDAK HADIR
        }

        $status = $row->status;
        if ($row->time_in !== null && $row->time_out === null && $date->isPast()) {
            $status = Attendance::ABSENSI_TIDAK_LENGKAP;
        }

        return [
            'attendance' => $row,
            'status' => $status,
        ];
    }

    /**
     * Rekap lengkap satu pegawai untuk satu bulan.
     * Jika $asOfDate diisi (YYYY-MM-DD), hanya hitung hari kerja sampai tanggal tsb
     * (mode pro-rata untuk penggajian tengah bulan / uji coba).
     */
    public function monthlyRecap(Employee $employee, int $year, int $month, ?string $asOfDate = null): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $overrides = WorkDateOverride::whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (WorkDateOverride $o) => $o->work_date->toDateString());

        // Hari kerja efektif: tanggal khusus menang atas pola mingguan + shift.
        $workDates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $isWork = isset($overrides[$key])
                ? (bool) $overrides[$key]->is_work_day
                : Setting::isWorkDay($day);

            if ($isWork) {
                $workDates[] = $key;
            }
        }

        if ($asOfDate !== null) {
            $workDates = array_values(array_filter(
                $workDates,
                fn (string $date) => $date <= $asOfDate,
            ));
        }

        $rows = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $row) => $row->attendance_date->toDateString());

        $hadir = 0;
        $terlambat = 0;
        $izin = 0;
        $tidakHadir = 0;
        $incomplete = 0;
        $details = [];

        foreach ($workDates as $date) {
            $carbon = Carbon::parse($date);
            $row = $rows->get($date);

            if ($row && $row->status === Attendance::IZIN) {
                $izin++;
                $status = Attendance::IZIN;
            } elseif ($row && $row->time_in !== null) {
                // Sudah ada catatan masuk (HADIR atau TERLAMBAT), bisa jadi kurang pulang.
                if ($row->time_out === null && $carbon->isPast()) {
                    $incomplete++;
                    $status = Attendance::ABSENSI_TIDAK_LENGKAP;
                } else {
                    if ($row->status === Attendance::TERLAMBAT) {
                        $terlambat++;
                    }
                    $hadir++;
                    $status = $row->status;
                }
            } else {
                // Tidak ada record sama sekali pada hari kerja.
                $tidakHadir++;
                $status = Attendance::TIDAK_HADIR;
            }

            $details[] = [
                'date' => $date,
                'day_name' => $this->dayName($date),
                'status' => $status,
                'time_in' => $row?->time_in,
                'time_out' => $row?->time_out,
                'late_minutes' => $row?->late_minutes,
                'source' => $row?->source,
            ];
        }

        return compact('workDates', 'hadir', 'terlambat', 'izin', 'tidakHadir', 'incomplete', 'details');
    }

    public function dayName(string $date): string
    {
        $names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return $names[(int) date('w', strtotime($date))];
    }
}
