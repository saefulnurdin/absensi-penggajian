<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Kasbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KasbonController extends Controller
{
    public function index(Request $request): View
    {
        $kasbons = Kasbon::with('employee')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $employees = Employee::orderBy('employee_id')->get(['id', 'name', 'employee_id']);

        return view('kasbon.index', compact('kasbons', 'employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'deduct_period' => ['required', 'date_format:Y-m'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        Kasbon::create($data + ['status' => Kasbon::PENDING]);

        return back()->with('success', 'Kasbon ditambahkan (PENDING). Setujui agar dipotong dari gaji.');
    }

    public function approve(Kasbon $kasbon): RedirectResponse
    {
        if ($kasbon->status !== Kasbon::PENDING) {
            return back()->withErrors(['kasbon' => 'Hanya kasbon PENDING yang bisa disetujui.']);
        }

        $kasbon->update(['status' => Kasbon::APPROVED, 'approved_at' => now()]);

        return back()->with('success', 'Kasbon disetujui dan akan dipotong dari gaji periode '.$kasbon->deduct_period.'.');
    }

    public function cancel(Kasbon $kasbon): RedirectResponse
    {
        if (! in_array($kasbon->status, [Kasbon::PENDING, Kasbon::APPROVED])) {
            return back()->withErrors(['kasbon' => 'Kasbon yang sudah dibayar tidak bisa dibatalkan.']);
        }

        $kasbon->update(['status' => Kasbon::CANCELLED]);

        return back()->with('success', 'Kasbon dibatalkan.');
    }

    public function destroy(Kasbon $kasbon): RedirectResponse
    {
        if ($kasbon->status === Kasbon::PAID) {
            return back()->withErrors(['kasbon' => 'Kasbon yang sudah dipotong dari gaji tidak bisa dihapus.']);
        }

        $kasbon->delete();

        return back()->with('success', 'Kasbon dihapus.');
    }
}