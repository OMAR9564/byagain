<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmailDelivery;
use App\Models\PushDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping for records that are operational rather than personal.
 *
 * Nothing here touches user content. Sources, highlights, mastery cards,
 * reviews and streak days are never pruned, never expired and never
 * garbage-collected — that is the point of the product (Constitution art. III,
 * FR-091).
 */
final class PruneEphemeralRecords extends Command
{
    protected $signature = 'byagain:prune {--dry-run : Report what would be removed, change nothing}';

    protected $description = 'Remove expired tokens, old delivery records and stale failed jobs. Never touches user content.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $retention = (int) config('byagain.mail.delivery_retention_days');

        $counts = [
            'password_reset_tokens' => $this->pruneResetTokens($dryRun),
            'email_deliveries' => $this->pruneDeliveries($retention, $dryRun),
            'push_deliveries' => $this->prunePushDeliveries($retention, $dryRun),
            'failed_jobs' => $this->pruneFailedJobs($dryRun),
        ];

        if ($dryRun) {
            $this->comment('Dry run — nothing was removed.');
        }

        foreach ($counts as $table => $count) {
            $this->line("{$table}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * Reset tokens are valid for an hour; anything older is dead weight that
     * happens to be a credential (FR-003).
     */
    private function pruneResetTokens(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subHours(2);

        $query = DB::table('password_reset_tokens')->where('created_at', '<', $cutoff);

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneDeliveries(int $retentionDays, bool $dryRun): int
    {
        $cutoff = Carbon::now()->subDays($retentionDays);

        // Through the query builder rather than the model: this runs in the
        // console with no signed-in user, and the intent is explicitly to
        // sweep every account's rows.
        $query = DB::table('email_deliveries')
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', [
                EmailDelivery::STATUS_SENT,
                EmailDelivery::STATUS_SKIPPED,
            ]);

        // Failures are kept regardless of age. An old failure is exactly the
        // thing somebody will want to look at later.
        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * Nudge records age out on the same schedule as mail ones — they answer
     * the same question and stop being asked it at the same point.
     *
     * `push_subscriptions` is deliberately **not** pruned: a valid
     * subscription works however old it is, and an invalid one is deleted at
     * send time the first moment we learn of it (FR-150,
     * contracts/console-and-jobs.md).
     */
    private function prunePushDeliveries(int $retentionDays, bool $dryRun): int
    {
        $cutoff = Carbon::now()->subDays($retentionDays);

        $query = DB::table('push_deliveries')
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', [
                PushDelivery::STATUS_SENT,
                PushDelivery::STATUS_SKIPPED,
            ]);

        // Failures kept regardless of age, as above.
        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneFailedJobs(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subDays(30);

        $query = DB::table('failed_jobs')->where('failed_at', '<', $cutoff);

        return $dryRun ? $query->count() : $query->delete();
    }
}
