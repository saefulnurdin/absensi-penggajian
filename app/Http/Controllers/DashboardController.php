<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();

        $stats = [
            'total_employees' => Employee::where('status', 'Aktif')->count(),
            'hadir' => Attendance::where('attendance_date', $today)
                ->whereIn('status', [Attendance::HADIR])->count(),
            'terlambat' => Attendance::where('attendance_date', $today)
                ->where('status', Attendance::TERLAMBAT)->count(),
            'izin' => Attendance::where('attendance_date', $today)
                ->where('status', Attendance::IZIN)->count(),
        ];

        // Tidak hadir = hari kerja hari ini, dikurangi yang punya catatan sama sekali.
        $recordedToday = Attendance::where('attendance_date', $today)->pluck('employee_id');
        $stats['tidak_hadir'] = Setting::isWorkDay($today)
            ? Employee::where('status', 'Aktif')
                ->whereNotIn('id', $recordedToday)
                ->count()
            : 0;

        $recent = Attendance::with('employee')
            ->where('attendance_date', $today)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $summary = Attendance::selectRaw('status, COUNT(*) as total')
            ->where('attendance_date', $today)
            ->groupBy('status')
            ->pluck('total', 'status');

        $company = Setting::get(Setting::COMPANY_NAME);

        return view('dashboard.index', compact('stats', 'recent', 'summary', 'company'));
    }
}
