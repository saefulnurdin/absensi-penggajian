<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\AttendanceService;
use App\Services\NotificationService;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService $payrollService,
        private AttendanceService $attendanceService,
        private NotificationService $notifier,
    ) {}

    public function index(Request $request): View
    {
        $period = $request->input('period', now()->format('Y-m'));
        [$year, $month] = explode('-', $period);

        $payrolls = Payroll::with('employee')
            ->where('period', $period)
            ->orderBy('employee_id')
            ->get();

        $employees = Employee::where('status', 'Aktif')->orderBy('employee_id')->get(['id', 'name', 'employee_id']);

        $summary = [
            'employees' => $payrolls->count(),
            'total_net' => (float) $payrolls->sum('net_salary'),
            'total_deduction' => (float) $payrolls->sum('deduction'),
            'total_kasbon' => (float) $payrolls->sum('kasbon_deduction'),
        ];

        return view('payroll.index', compact('period', 'year', 'month', 'payrolls', 'employees', 'summary'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $asOf = $data['as_of_date'] ?? null;

        if ($asOf !== null) {
            $bod = Carbon::createFromFormat('Y-m-d', $data['period'].'-01')->startOfMonth();
            $eom = $bod->copy()->endOfMonth();

            if ($asOf < $bod->toDateString() || $asOf > $eom->toDateString()) {
                return back()->withErrors(['as_of_date' => 'Tanggal pro-rata harus di dalam periode '.$data['period'].'.']);
            }
        }

        $created = $this->payrollService->generateForPeriod($data['period'], $asOf);

        return back()->with(
            'success',
            'Payroll periode '.$data['period'].($asOf !== null ? ' (pro-rata s.d. '.$asOf.')' : '')
                .' dihitung ulang untuk '.count($created).' pegawai.',
        );
    }

    public function show(Payroll $payroll): View
    {
        [$year, $month] = explode('-', $payroll->period);
        $recap = $this->attendanceService->monthlyRecap($payroll->employee, (int) $year, (int) $month, $payroll->as_of_date);

        return view('payroll.show', compact('payroll', 'recap'));
    }

    public function slip(Payroll $payroll): View
    {
        [$year, $month] = explode('-', $payroll->period);
        $recap = $this->attendanceService->monthlyRecap($payroll->employee, (int) $year, (int) $month, $payroll->as_of_date);

        return view('payroll.slip', compact('payroll', 'recap'));
    }

    public function process(Payroll $payroll): RedirectResponse
    {
        $payroll->update(['status' => 'DIPROSES']);

        [$year, $month] = explode('-', $payroll->period);
        $recap = $this->attendanceService->monthlyRecap($payroll->employee, (int) $year, (int) $month, $payroll->as_of_date);

        $this->notifier->payslip($payroll, $recap);
        $this->notifier->payrollProcessed($payroll);
        $this->notifyPayslipWhatsApp($payroll, $recap);

        return back()->with('success', 'Gaji periode '.$payroll->period.' diproses dan email dikirim.');
    }

    public function sendMany(Request $request): RedirectResponse
    {
        $ids = $request->input('payroll_ids', []);
        if (empty($ids)) {
            return back()->withErrors(['payroll_ids' => 'Pilih minimal satu payroll.']);
        }

        $count = 0;
        foreach (Payroll::with('employee')->whereIn('id', $ids)->get() as $index => $payroll) {
            $payroll->update(['status' => 'DIPROSES']);

            [$year, $month] = explode('-', $payroll->period);
            $recap = $this->attendanceService->monthlyRecap($payroll->employee, (int) $year, (int) $month, $payroll->as_of_date);

            $this->notifier->payslip($payroll, $recap);
            $this->notifier->payrollProcessed($payroll);
            SendWhatsAppMessage::dispatch(
                $payroll->employee->phone_wa,
                $this->waPayslipText($payroll, $recap),
                'slip-gaji',
                Payroll::class,
                $payroll->id,
            )->delay(now()->addSeconds(($index * 5) + 2));
            $count++;
        }

        return back()->with('success', "Email & WhatsApp slip gaji dijadwalkan untuk {$count} pegawai.");
    }

    private function notifyPayslipWhatsApp(Payroll $payroll, array $recap): void
    {
        SendWhatsAppMessage::dispatch(
            $payroll->employee->phone_wa,
            $this->waPayslipText($payroll, $recap),
            'slip-gaji',
            Payroll::class,
            $payroll->id,
        );
    }

    private function waPayslipText(Payroll $payroll, array $recap): string
    {
        $period = Carbon::createFromFormat('Y-m', $payroll->period)->translatedFormat('F Y');
        $employee = $payroll->employee;

        $lines = [
            'SLIP GAJI '.$period,
            'Nama: '.$employee->name.' ('.$employee->employee_id.')',
            '',
            'Hadir: '.$payroll->hadir_count,
            'Izin: '.$payroll->izin_count,
            'Tidak Hadir: '.$payroll->absent_count,
            'Hari Kerja (pro-rata): '.$payroll->work_days,
            '',
            'Gaji Pokok: Rp'.number_format((float) $payroll->base_salary, 0, ',', '.'),
            'Potongan Tidak Hadir: -Rp'.number_format((float) $payroll->deduction, 0, ',', '.'),
            'Potongan Kasbon: -Rp'.number_format((float) $payroll->kasbon_deduction, 0, ',', '.'),
            '',
            'GAJI BERSIH: Rp'.number_format((float) $payroll->net_salary, 0, ',', '.'),
        ];

        if ($payroll->as_of_date) {
            $lines[] = '';
            $lines[] = 'Dihitung s.d. '.$payroll->as_of_date;
        }

        $lines[] = '';
        $lines[] = 'Dikirim otomatis dari Sistem Absensi RFID';

        return implode(PHP_EOL, $lines);
    }
}
