<?php

namespace App\Mail;

use App\Models\Payroll;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PayrollProcessedMail extends Mailable
{
    public function __construct(public Payroll $payroll) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gaji Periode '.$this->payroll->period.' Telah Diproses',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payroll-processed',
            with: [
                'payroll' => $this->payroll,
                'employee' => $this->payroll->employee,
            ],
        );
    }
}
