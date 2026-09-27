<?php

namespace App\Mail;

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PayslipMail extends Mailable
{
    public function __construct(
        public Payroll $payroll,
        public array $recap,
    ) {}

    public function envelope(): Envelope
    {
        [$year, $month] = explode('-', $this->payroll->period);

        return new Envelope(
            subject: 'Slip Gaji & Rekap Absensi '.Carbon::create($year, $month, 1)->translatedFormat('F Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payslip',
            with: [
                'employee' => $this->payroll->employee,
                'payroll' => $this->payroll,
                'recap' => $this->recap,
            ],
        );
    }
}
