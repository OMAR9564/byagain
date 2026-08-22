<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\View\View;

/**
 * Turning the emails off, from inside the email.
 *
 * No sign-in required, on purpose. Somebody who wants these to stop should not
 * first have to remember a password — that is how "unsubscribe" becomes "mark
 * as spam". The link is signed with the application key instead, so it cannot
 * be forged or guessed and needs no token table of its own (FR-065, R-12).
 *
 * The route is bound by id rather than through the ownership scope, since
 * there is no signed-in user to scope to; the signature is the authorisation.
 */
final class UnsubscribeController extends Controller
{
    public function __invoke(int $user, string $type): View
    {
        abort_unless(
            in_array($type, [EmailDelivery::TYPE_DAILY, EmailDelivery::TYPE_REMINDER], true),
            404,
        );

        $account = User::query()->findOrFail($user);

        $column = $type === EmailDelivery::TYPE_DAILY
            ? 'daily_email_enabled'
            : 'reminder_email_enabled';

        // Idempotent: following the link twice is not an error, and mail
        // clients do prefetch links.
        $account->{$column} = false;
        $account->save();

        return view('mail.unsubscribed', ['type' => $type]);
    }
}
