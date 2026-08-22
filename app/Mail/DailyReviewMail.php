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
 * The morning mail, with the cards embedded in it.
 *
 * The cards come from the review's own rows — nothing is re-sampled at send
 * time. That is what guarantees the email and the app show the same passages
 * in the same order (FR-061); re-sampling here would produce two different
 * "todays" and quietly break the product's core promise.
 */
final class DailyReviewMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly Review $review,
        public readonly EmailDelivery $delivery,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.daily.subject', ['date' => $this->review->review_date->toFormattedDateString()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: null,
            view: 'mail.daily',
            // A plain-text alternative always, not only when asked for: some
            // readers see it, and a mail with no text part looks like spam to
            // a filter (FR-066).
            text: 'mail.daily-text',
            with: [
                'user' => $this->user,
                'items' => $this->review->items,
                'reviewUrl' => route('review.show'),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    /**
     * A signed link, so it cannot be forged and needs no session — the reader
     * should be able to turn these off from the mail itself, without first
     * finding their password (FR-065, R-12).
     */
    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('unsubscribe', [
            'user' => $this->user->id,
            'type' => EmailDelivery::TYPE_DAILY,
        ]);
    }
}
