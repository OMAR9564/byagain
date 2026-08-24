<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

/**
 * Print a VAPID keypair. Write nothing.
 *
 * The keys go in `.env`, and `.env` is not ours to touch (Constitution
 * art. III) — so this prints three lines a human pastes. That is one step more
 * than a command that edited the file, and one step fewer than getting the
 * `openssl` incantation right by hand.
 *
 * No `--force` and no confirmation, because there is nothing to overwrite: two
 * runs produce two unrelated pairs and neither of them goes anywhere.
 */
final class GenerateVapidKeys extends Command
{
    protected $signature = 'byagain:vapid-keys';

    protected $description = 'Generate a VAPID keypair for browser notifications and print it. Writes nothing.';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            $this->error('Could not generate a keypair: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT="mailto:you@example.com"');
        $this->newLine();

        $this->comment('Paste these into .env yourself — this command wrote nothing.');
        $this->comment('Replace the subject with a real address you would answer.');

        // Worth saying out loud: the public key is baked into every
        // subscription a browser has already granted.
        $this->comment('Generating a new pair invalidates every existing subscription.');

        return self::SUCCESS;
    }
}
