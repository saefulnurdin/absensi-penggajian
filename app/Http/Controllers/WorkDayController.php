<?php

namespace App\Http\Controllers;

use App\Models\WorkDateOverride;
use App\Models\WorkDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class WorkDayController extends Controller
{
    public function index(): View
    {
        $workDays = WorkDay::orderBy('day_index')->get()
            ->keyBy('day_index');

        $overrides = WorkDateOverride::orderByDesc('work_date')->paginate(10);

        return view('work-days.index', compact('workDays', 'overrides'));
    }

    public function update(Request $request): RedirectResponse
    {
        $active = array_map('intval', $request->input('active', []));

        // index dimulai dari 0 (Minggu) sampai 6 (Sabtu).
        foreach (range(0, 6) as $index) {
            WorkDay::updateOrCreate(
                ['day_index' => $index],
                ['day_name' => $this->nameOf($index), 'is_work_day' => in_array($index, $active, true)]
            );
        }

        Cache::flush();

        return redirect()->route('work-days.index')->with('success', 'Pengaturan hari kerja disimpan.');
    }

    private function nameOf(int $index): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$index];
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'is_work_day' => ['sometimes', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $start = $request->input('start_time');
                if ($value !== null && $start !== null && (string) trim($value) === (string) $start) {
                    $fail('Jam selesai tidak boleh sama dengan jam mulai.');
                }
            }],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        WorkDateOverride::updateOrCreate(
            ['work_date' => $data['work_date']],
            [
                'is_work_day' => (bool) ($data['is_work_day'] ?? false),
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'label' => $data['label'] ?? null,
            ]
        );

        Cache::forget('app_settings');

        return redirect()->route('work-days.index')
            ->with('success', 'Tanggal khusus untuk '.$data['work_date'].' disimpan.');
    }

    public function destroyOverride(WorkDateOverride $override): RedirectResponse
    {
        $override->delete();

        return redirect()->route('work-days.index')
            ->with('success', 'Tanggal khusus dihapus.');
    }
}
