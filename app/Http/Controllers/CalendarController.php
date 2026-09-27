<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request): View
    {
        $month = (int) ($request->input('month', now()->month));
        $year = (int) ($request->input('year', now()->year));
        $employeeId = $request->integer('employee_id', Employee::where('status', 'Aktif')->value('id'));

        $employees = Employee::where('status', 'Aktif')->orderBy('employee_id')->get(['id', 'name', 'employee_id']);
        $employee = Employee::with([
            'attendance' => fn ($q) => $q->whereYear('attendance_date', $year)->whereMonth('attendance_date', $month),
        ])->findOrFail($employeeId);

        $recap = $this->service->monthlyRecap($employee, $year, $month);

        return view('attendance.calendar', compact('employees', 'employee', 'employeeId', 'month', 'year', 'recap'));
    }
}
