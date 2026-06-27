<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NotificationDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Collection $notifications,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->notifications->count();

        return new Envelope(
            subject: "ProgressOS — {$count} new ".($count === 1 ? 'notification' : 'notifications'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification-digest',
        );
    }
}
