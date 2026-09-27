<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Kasbon;
use App\Models\Payroll;
use Illuminate\Support\Collection;

class PayrollService
{
    public function __construct(private AttendanceService $attendanceService) {}

    /**
     * Menghitung ulang payroll semua pegawai aktif untuk satu periode (YYYY-MM).
     * $asOfDate (YYYY-MM-DD) opsional: hitung pro-rata sampai tanggal tsb.
     */
    public function generateForPeriod(string $period, ?string $asOfDate = null): Collection|array
    {
        [$year, $month] = explode('-', $period);
        $employees = Employee::where('status', 'Aktif')->with('attendance')->get();
        $created = [];

        foreach ($employees as $employee) {
            $created[] = $this->generateForEmployee($employee, (int) $year, (int) $month, $asOfDate);
        }

        return $created;
    }

    public function generateForEmployee(Employee $employee, int $year, int $month, ?string $asOfDate = null): Payroll
    {
        $recap = $this->attendanceService->monthlyRecap($employee, $year, $month, $asOfDate);
        $period = sprintf('%04d-%02d', $year, $month);

        $baseSalary = (float) $employee->base_salary;
        $workDays = count($recap['workDates']);
        $dailySalary = $workDays > 0 ? round($baseSalary / $workDays) : 0.0;
        $deduction = round($recap['tidakHadir'] * $dailySalary);
        $kasbonDeduction = $this->aggregateKasbon($employee->id, $period);
        $netSalary = max(0, round($baseSalary - $deduction - $kasbonDeduction));

        return Payroll::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $period],
            [
                'as_of_date' => $asOfDate,
                'work_days' => $workDays,
                'hadir_count' => $recap['hadir'],
                'late_count' => $recap['terlambat'],
                'izin_count' => $recap['izin'],
                'absent_count' => $recap['tidakHadir'],
                'incomplete_count' => $recap['incomplete'],
                'base_salary' => $baseSalary,
                'daily_salary' => $dailySalary,
                'deduction' => $deduction,
                'kasbon_deduction' => $kasbonDeduction,
                'net_salary' => $netSalary,
                'status' => Payroll::where('employee_id', $employee->id)->where('period', $period)->value('status') ?? 'DRAFT',
            ]
        );
    }

    /**
     * Menjumlah kasbon yang akan dipotong pada periode tertentu lalu menandainya PAID.
     * Filter 'APPROVED' dan 'PAID': regenerasi payroll periode yang sama tetap konsisten.
     */
    private function aggregateKasbon(int $employeeId, string $period): float
    {
        $kasbons = Kasbon::where('employee_id', $employeeId)
            ->where('deduct_period', $period)
            ->whereIn('status', [Kasbon::APPROVED, Kasbon::PAID])
            ->get();

        $total = (float) $kasbons->sum('amount');

        foreach ($kasbons as $kasbon) {
            if ($kasbon->status !== Kasbon::PAID) {
                $kasbon->update([
                    'status' => Kasbon::PAID,
                    'paid_in_period' => $period,
                ]);
            }
        }

        return round($total);
    }
}
