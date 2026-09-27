<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use App\Mail\AttendanceNotificationMail;
use App\Mail\PayrollProcessedMail;
use App\Mail\PayslipMail;
use App\Models\EmailLog;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Notifikasi absensi ke pegawai (hadir/terlambat/pulang/izin/tidak hadir).
     */
    public function attendanceStatus(Employee $employee, string $event, array $payload = []): void
    {
        $mail = new AttendanceNotificationMail(
            employee: $employee,
            event: $event,
            time: $payload['time'] ?? null,
            lateMinutes: $payload['late_minutes'] ?? null,
            date: $payload['date'] ?? null,
            customMessage: $payload['message'] ?? null,
        );

        $recipient = $this->resolveEmployeeEmail($employee);

        $this->send($mail, $recipient, 'attendance:'.$event, $employee);
    }

    /**
     * Alert ke admin (anomali absensi, perangkat offline, dll).
     */
    public function alertAdmin(string $subject, string $message, array $context = []): void
    {
        $adminEmail = config('mail.to_admin');
        $mail = new AdminAlertMail($subject, $message, $context);

        $this->send($mail, $adminEmail, 'admin-alert', null);
    }

    /**
     * Kirim slip gaji + rekap absensi bulanan ke pegawai.
     */
    public function payslip(Payroll $payroll, array $recap): void
    {
        $mail = new PayslipMail($payroll, $recap);
        $recipient = $this->resolveEmployeeEmail($payroll->employee);

        $this->send($mail, $recipient, 'payslip', $payroll->employee, $payroll);
    }

    /**
     * Notifikasi gaji telah diproses.
     */
    public function payrollProcessed(Payroll $payroll): void
    {
        $mail = new PayrollProcessedMail($payroll);
        $recipient = $this->resolveEmployeeEmail($payroll->employee);

        $this->send($mail, $recipient, 'payroll-processed', $payroll->employee, $payroll);
    }

    public function send(object $mailable, ?string $recipient, string $type, ?Employee $employee = null, ?Payroll $payroll = null): bool
    {
        // Jika pegawai tidak punya email, kirim ke admin dan catat di log.
        if (blank($recipient)) {
            $recipient = config('mail.to_admin');
            $type = 'vip-admin-fallback';
        }

        try {
            Mail::to($recipient)->send($mailable);

            EmailLog::create([
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $mailable->subject ?? 'Email',
                'body_preview' => $mailable->subject ?? 'Email',
                'status' => 'SENT',
                'related_type' => $payroll ? Payroll::class : null,
                'related_id' => $payroll?->id,
            ]);

            return true;
        } catch (\Throwable $e) {
            EmailLog::create([
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $mailable->subject ?? 'Email',
                'body_preview' => 'Kesalahan: '.$e->getMessage(),
                'status' => 'FAILED',
                'related_type' => $payroll ? Payroll::class : null,
                'related_id' => $payroll?->id,
            ]);

            report($e);

            return false;
        }
    }

    private function resolveEmployeeEmail(Employee $employee): ?string
    {
        return ! blank($employee->email) ? $employee->email : null;
    }
}
