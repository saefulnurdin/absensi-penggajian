<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(): View
    {
        $shifts = Shift::orderBy('start_time')->get();

        return view('shifts.index', compact('shifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                if ((string) trim($value) === (string) $request->input('start_time')) {
                    $fail('Jam selesai tidak boleh sama dengan jam mulai.');
                }
            }],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
        ]);

        Shift::create($data + ['is_active' => true]);

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function toggle(Shift $shift): RedirectResponse
    {
        $shift->update(['is_active' => ! $shift->is_active]);

        return redirect()->route('shifts.index')->with('success', $shift->is_active ? 'Shift diaktifkan.' : 'Shift dinonaktifkan.');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift dihapus.');
    }
}
