<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Setting;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = Employee::with(['rfidCard', 'shift'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('employee_id', 'like', '%'.$request->search.'%');
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderBy('employee_id')
            ->paginate(10);

        return view('employees.index', compact('employees'));
    }

    public function create(): View
    {
        $employee = new Employee([
            'base_salary' => 0,
            'status' => 'Aktif',
            'hire_date' => now()->toDateString(),
            'employment_type' => 'KONTRAK',
        ]);

        $shifts = Shift::orderBy('start_time')->get();

        return view('employees.form', compact('employee', 'shifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $employee = Employee::create($data);

        $this->syncRfid($employee, $request->input('rfid_uid'), $request->input('rfid_note'));

        Setting::forgetCache();

        return redirect()->route('employees.index')
            ->with('success', "Pegawai {$employee->name} berhasil ditambahkan.");
    }

    public function edit(Employee $employee): View
    {
        $shifts = Shift::orderBy('start_time')->get();

        return view('employees.form', compact('employee', 'shifts'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);
        $employee->update($data);

        $this->syncRfid($employee, $request->input('rfid_uid'), $request->input('rfid_note'));

        return redirect()->route('employees.index')
            ->with('success', "Pegawai {$employee->name} berhasil diperbarui.");
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Pegawai berhasil dihapus.');
    }

    private function validated(Request $request, ?Employee $ignore = null): array
    {
        return $request->validate([
            'employee_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('employees')->ignore($ignore?->id)],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', Rule::in(['KARTAP', 'KONTRAK', 'MAGANG'])],
            'contract_start' => [Rule::requiredIf(in_array($request->input('employment_type'), ['KONTRAK', 'MAGANG'], true)), 'nullable', 'date'],
            'contract_end' => [Rule::requiredIf(in_array($request->input('employment_type'), ['KONTRAK', 'MAGANG'], true)), 'nullable', 'date', 'after_or_equal:contract_start'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Aktif', 'Nonaktif'])],
            'hire_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_wa' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'rfid_uid' => ['nullable', 'string', 'max:32',
                Rule::unique('rfid_cards', 'uid')->whereNot('employee_id', $ignore?->id ?? 0)],
            'rfid_note' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * Daftarkan / perbarui kartu RFID pegawai.
     * UID kosong -> lepaskan kartu aktif.
     */
    private function syncRfid(Employee $employee, ?string $uid, ?string $note): void
    {
        $uid = $uid !== null ? strtoupper(trim($uid)) : null;

        if (blank($uid)) {
            RfidCard::where('employee_id', $employee->id)->where('is_active', true)->update(['is_active' => false]);

            return;
        }

        $card = RfidCard::where('employee_id', $employee->id)->where('is_active', true)->first();

        if ($card) {
            $card->update(['uid' => $uid, 'note' => $note]);
        } else {
            RfidCard::create([
                'employee_id' => $employee->id,
                'uid' => $uid,
                'is_active' => true,
                'note' => $note,
            ]);
        }
    }
}
