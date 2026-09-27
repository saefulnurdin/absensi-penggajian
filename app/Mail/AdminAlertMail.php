<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminAlertMail extends Mailable
{
    public function __construct(
        public string $alertSubject,
        public string $alertMessage,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ALERT] '.$this->alertSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-alert',
            with: [
                'alertSubject' => $this->alertSubject,
                'alertMessage' => $this->alertMessage,
                'context' => $this->context,
            ],
        );
    }
}
