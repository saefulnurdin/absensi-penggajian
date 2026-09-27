<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\RfidUnknown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidCardController extends Controller
{
    public function index(Request $request): View
    {
        $cards = RfidCard::with('employee')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('uid', 'like', '%'.$request->search.'%')
                    ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', '%'.$request->search.'%'));
            })
            ->orderByDesc('updated_at')
            ->paginate(10);

        $employees = Employee::where('status', 'Aktif')
            ->doesntHave('rfidCard')
            ->orderBy('employee_id')
            ->get();

        $pending = RfidUnknown::orderByDesc('last_seen_at')->paginate(10, ['*'], 'pending');

        return view('rfid-cards.index', compact('cards', 'employees', 'pending'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'uid' => ['required', 'string', 'max:32', Rule::unique('rfid_cards', 'uid')],
            'note' => ['nullable', 'string', 'max:100'],
        ]);

        // Pegawai yang dipilih harusnya belum punya kartu aktif, jaga-jaga.
        RfidCard::where('employee_id', $data['employee_id'])->where('is_active', true)
            ->update(['is_active' => false]);

        RfidCard::create([
            'employee_id' => $data['employee_id'],
            'uid' => strtoupper($data['uid']),
            'is_active' => true,
            'note' => $data['note'] ?? null,
        ]);

        RfidUnknown::where('uid', strtoupper($data['uid']))->delete();

        return redirect()->route('rfid-cards.index')->with('success', 'Kartu RFID berhasil didaftarkan.');
    }

    public function assign(Request $request, RfidCard $card): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        DB::transaction(function () use ($card, $data) {
            RfidCard::where('employee_id', $data['employee_id'])->where('is_active', true)
                ->where('id', '!=', $card->id)
                ->update(['is_active' => false]);

            $card->update(['employee_id' => $data['employee_id'], 'is_active' => true]);
        });

        return redirect()->route('rfid-cards.index')->with('success', 'Kartu RFID dipindahkan ke pegawai lain.');
    }

    public function toggle(RfidCard $card): RedirectResponse
    {
        $card->update(['is_active' => ! $card->is_active]);

        return redirect()->route('rfid-cards.index')
            ->with('success', $card->is_active ? 'Kartu diaktifkan.' : 'Kartu dinonaktifkan.');
    }

    public function destroy(RfidCard $card): RedirectResponse
    {
        $card->delete();

        return redirect()->route('rfid-cards.index')->with('success', 'Kartu RFID dihapus.');
    }
}
