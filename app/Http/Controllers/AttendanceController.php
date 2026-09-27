<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request): View
    {
        $attendance = Attendance::with('employee')
            ->when($request->filled('date'), fn ($q) => $q->where('attendance_date', $request->date))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->orderByDesc('attendance_date')
            ->orderByDesc('time_in')
            ->paginate(15)
            ->withQueryString();

        $employees = Employee::where('status', 'Aktif')->orderBy('employee_id')->get(['id', 'name', 'employee_id']);

        return view('attendance.index', compact('attendance', 'employees'));
    }

    public function create(): View
    {
        $employees = Employee::where('status', 'Aktif')->orderBy('employee_id')->get(['id', 'name', 'employee_id', 'position']);
        $attendance = new Attendance;
        $attendance->attendance_date = today();

        return view('attendance.form', compact('employees', 'attendance'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:'.implode(',', Attendance::ALL_STATUSES)],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $date = $data['attendance_date'];

        $exists = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $date)
            ->exists();

        if ($exists) {
            return back()->withErrors(['attendance_date' => 'Absensi untuk pegawai pada tanggal tersebut sudah ada.'])
                ->withInput();
        }

        $payload = array_merge($data, [
            'attendance_date' => $date,
            'time_in' => $this->withSeconds($data['time_in'] ?? null),
            'time_out' => $this->withSeconds($data['time_out'] ?? null),
            'source' => 'ADMIN',
            'edited_by' => auth()->user()->name,
        ]);

        if ($data['status'] === Attendance::IZIN) {
            unset($payload['time_in'], $payload['time_out']);
        }

        Attendance::create($payload);

        return redirect()->route('attendance.index', ['date' => $date])
            ->with('success', 'Absensi manual berhasil ditambahkan.');
    }

    public function edit(Attendance $attendance): View
    {
        $employees = Employee::where('status', 'Aktif')->orderBy('employee_id')->get(['id', 'name', 'employee_id', 'position']);

        return view('attendance.form', compact('employees', 'attendance'));
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:'.implode(',', Attendance::ALL_STATUSES)],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'edit_reason' => ['required', 'string', 'max:255'],
        ]);

        $duplicate = Attendance::where('employee_id', $data['employee_id'])
            ->where('attendance_date', $data['attendance_date'])
            ->where('id', '!=', $attendance->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['attendance_date' => 'Pegawai tersebut sudah memiliki absensi pada tanggal ini.'])
                ->withInput();
        }

        $payload = array_merge($data, [
            'attendance_date' => $data['attendance_date'],
            'time_in' => $this->withSeconds($data['time_in'] ?? null),
            'time_out' => $this->withSeconds($data['time_out'] ?? null),
            'edited_by' => auth()->user()->name,
        ]);

        if ($data['status'] === Attendance::IZIN) {
            $payload['time_in'] = null;
            $payload['time_out'] = null;
        }

        $attendance->update($payload);

        return redirect()->route('attendance.index', ['date' => $attendance->attendance_date->toDateString()])
            ->with('success', 'Data absensi diperbarui.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $date = $attendance->attendance_date->toDateString();
        $attendance->delete();

        return redirect()->route('attendance.index', ['date' => $date])
            ->with('success', 'Data absensi dihapus.');
    }

    private function withSeconds(?string $hm): ?string
    {
        return $hm !== null ? $hm.':00' : null;
    }
}
