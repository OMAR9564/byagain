<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grants the admin role.
 *
 * Deliberately the only way. There is no path from the interface to becoming
 * an administrator — not a hidden form, not a setting, not a first-user-wins
 * rule. Someone with shell access on the server is already trusted; someone
 * with a browser is not (FR-009, Assumptions).
 */
final class PromoteAdmin extends Command
{
    protected $signature = 'byagain:promote-admin {email} {--demote : Return the account to an ordinary reader}';

    protected $description = 'Grant or revoke the admin role for an account. The only way to become an administrator.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No account with the address {$email}.");

            return self::FAILURE;
        }

        $demote = (bool) $this->option('demote');

        $user->role = $demote ? User::ROLE_USER : User::ROLE_ADMIN;
        $user->save();

        $this->info($demote
            ? "{$email} is now an ordinary reader."
            : "{$email} is now an administrator.");

        return self::SUCCESS;
    }
}
