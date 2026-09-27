<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $statuses = [
            'company_name' => Setting::get(Setting::COMPANY_NAME),
            'work_start' => Setting::get(Setting::WORK_START),
            'work_end' => Setting::get(Setting::WORK_END),
            'break_start' => Setting::get(Setting::BREAK_START),
            'break_end' => Setting::get(Setting::BREAK_END),
            'late_tolerance' => Setting::get(Setting::LATE_TOLERANCE),
            'wa_admin_phone' => Setting::get(Setting::WA_ADMIN_PHONE),
            'wa_recap_time' => Setting::get(Setting::WA_RECAP_TIME),
        ];

        return view('settings.index', compact('statuses'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i'],
            'break_start' => ['required', 'date_format:H:i'],
            'break_end' => ['required', 'date_format:H:i', 'after:break_start'],
            'late_tolerance' => ['required', 'integer', 'min:0', 'max:180'],
            'wa_admin_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'wa_recap_time' => ['nullable', 'date_format:H:i'],
        ]);

        $map = [
            'company_name' => Setting::COMPANY_NAME,
            'work_start' => Setting::WORK_START,
            'work_end' => Setting::WORK_END,
            'break_start' => Setting::BREAK_START,
            'break_end' => Setting::BREAK_END,
            'late_tolerance' => Setting::LATE_TOLERANCE,
            'wa_admin_phone' => Setting::WA_ADMIN_PHONE,
            'wa_recap_time' => Setting::WA_RECAP_TIME,
        ];

        foreach ($map as $form => $dbKey) {
            Setting::updateOrCreate(['key' => $dbKey], ['value' => $data[$form]]);
        }

        Setting::forgetCache();

        return redirect()->route('settings.index')->with('success', 'Pengaturan sistem disimpan.');
    }
}
