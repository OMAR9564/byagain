<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EmailDelivery;
use App\Models\Review;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * The evening nudge.
 *
 * Carries no cards — just how many are left and one link. The point is to be
 * easy to ignore: a reminder that argues with you is a reminder you
 * unsubscribe from (FR-062).
 */
final class EveningReminderMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly Review $review,
        public readonly EmailDelivery $delivery,
        public readonly int $remaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.reminder.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reminder',
            text: 'mail.reminder-text',
            with: [
                'user' => $this->user,
                'remaining' => $this->remaining,
                'reviewUrl' => route('review.show'),
                'unsubscribeUrl' => URL::signedRoute('unsubscribe', [
                    'user' => $this->user->id,
                    'type' => EmailDelivery::TYPE_REMINDER,
                ]),
            ],
        );
    }
}
