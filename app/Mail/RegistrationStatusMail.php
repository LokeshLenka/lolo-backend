<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $status,
        public array $participantData
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'initiated' => 'Registration Initiated - Action Required',
            'success'   => 'Registration Confirmed - Your Ticket Inside',
            'failed'    => 'Registration Failed - Payment Issue',
        ];

        return new Envelope(
            subject: $subjects[$this->status] ?? 'Event Registration Update',
        );
    }

    public function content(): Content
    {
        // Dynamically loads the blade file based on the status
        return new Content(
            view: "emails.registration.{$this->status}",
        );
    }
}
