<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AttendanceNotificationMail extends Mailable
{
    /**
     * $event: hadir | terlambat | pulang | izin | tidak_hadir
     */
    public function __construct(
        public Employee $employee,
        public string $event,
        public ?string $time = null,
        public ?int $lateMinutes = null,
        public ?string $date = null,
        public ?string $customMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $titles = [
            'hadir' => 'Konfirmasi Absensi Masuk',
            'terlambat' => 'Pemberitahuan Keterlambatan',
            'pulang' => 'Konfirmasi Absensi Pulang',
            'izin' => 'Pemberitahuan Izin',
            'tidak_hadir' => 'Pemberitahuan Tidak Masuk',
        ];

        return new Envelope(subject: ($titles[$this->event] ?? 'Notifikasi Absensi').' - '.$this->employee->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.attendance-notification',
            with: [
                'employee' => $this->employee,
                'event' => $this->event,
                'time' => $this->time,
                'lateMinutes' => $this->lateMinutes,
                'date' => $this->date,
                'customMessage' => $this->customMessage,
            ],
        );
    }
}
