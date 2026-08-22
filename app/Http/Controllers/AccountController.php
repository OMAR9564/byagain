<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Account deletion — the one place in byagain where data really is destroyed.
 *
 * Everywhere else the rule is that user content is hidden, never deleted
 * (Constitution art. III). This is the deliberate exception, because it is the
 * user's own request about their own data, and "delete my account" has to mean
 * what it says (FR-008).
 */
final class AccountController extends Controller
{
    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            // Sources, highlights, mastery cards, reviews, review items and
            // streak days all cascade from the user row.
            $user->sources()->delete();
            $user->reviews()->delete();
            $user->streakDays()->delete();

            // Delivery rows are kept but stripped of the address: they are
            // operational records the admin panel reports on, and an
            // anonymised row cannot be traced back to a person.
            $user->emailDeliveries()->update(['recipient' => 'deleted@invalid']);

            // The row itself stays so foreign keys and audit logs remain
            // intact, but nothing identifying survives on it.
            $user->forceFill([
                'name' => 'Deleted account',
                'email' => 'deleted+'.Str::uuid()->toString().'@invalid',
                'email_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'status' => User::STATUS_DELETED,
                'daily_email_enabled' => false,
                'reminder_email_enabled' => false,
            ])->save();
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('settings.account.deleted'));
    }
}
