<?php

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentAccepted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Telemedicine Appointment Accepted');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-accepted',
            with: [
                'appointment' => $this->appointment,
                'calendarUrl' => $this->calendarUrl(),
            ],
        );
    }

    private function calendarUrl(): string
    {
        $start = CarbonImmutable::parse($this->appointment['requested_at'])->utc();
        $end = isset($this->appointment['requested_end_at'])
            ? CarbonImmutable::parse($this->appointment['requested_end_at'])->utc()
            : $start->addHour();

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => 'Telemedicine appointment with '.$this->appointment['preferred_doctor'],
            'dates' => $start->format('Ymd\THis\Z').'/'.$end->format('Ymd\THis\Z'),
            'details' => 'Join video appointment: '.$this->appointment['video_url'],
            'location' => $this->appointment['facility_address'],
        ]);
    }
}
