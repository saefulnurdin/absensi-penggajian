<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecapController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request): View
    {
        $month = (int) ($request->input('month', now()->month));
        $year = (int) ($request->input('year', now()->year));
        $employeeId = $request->integer('employee_id');

        $employees = Employee::where('status', 'Aktif')->orderBy('employee_id')->get(['id', 'name', 'employee_id', 'position']);

        $rows = collect();
        if ($employeeId) {
            $employee = Employee::findOrFail($employeeId);
            $recap = $this->service->monthlyRecap($employee, $year, $month);
            $rows = collect()->push($this->toRow($employee, $recap));
        } else {
            foreach ($employees as $employee) {
                $recap = $this->service->monthlyRecap($employee, $year, $month);
                $rows->push($this->toRow($employee, $recap));
            }
        }

        $period = sprintf('%04d-%02d', $year, $month);

        return view('attendance.rekap', compact('rows', 'employees', 'month', 'year', 'employeeId', 'period'));
    }

    private function toRow(Employee $employee, array $recap): array
    {
        return [
            'employee' => $employee,
            'work_days' => count($recap['workDates']),
            'hadir' => $recap['hadir'],
            'terlambat' => $recap['terlambat'],
            'izin' => $recap['izin'],
            'tidak_hadir' => $recap['tidakHadir'],
            'incomplete' => $recap['incomplete'],
            'details' => $recap['details'],
        ];
    }
}
