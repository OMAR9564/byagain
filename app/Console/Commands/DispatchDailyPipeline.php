<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\MailDispatcher;
use App\Services\Push\PushDispatcher;
use App\Services\Review\ReviewBuilder;
use App\Services\Time\LocalDayResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The one scheduled command the product runs on: every five minutes, work out
 * whose local clock has just reached their send time, build their review, and
 * queue their mail.
 *
 * Sweeping every five minutes rather than scheduling per user is what makes
 * timezones tractable. There is no per-user cron to keep in sync when someone
 * moves country or changes their send time — the next sweep simply finds them
 * in a different window (SC-013).
 *
 * Running twice over the same window is harmless. Both the review and the
 * delivery are protected by unique indexes, so a duplicate loses at the
 * database rather than being prevented by a check that could race (FR-090).
 */
final class DispatchDailyPipeline extends Command
{
    protected $signature = 'byagain:dispatch-daily
                            {--user= : Restrict to a single user id}
                            {--now= : Treat this instant as the current time}
                            {--dry-run : Report what would happen, change nothing}';

    protected $description = 'Build reviews and queue the daily and reminder emails for users whose local send time has arrived.';

    public function handle(
        LocalDayResolver $days,
        ReviewBuilder $builder,
        MailDispatcher $dispatcher,
        PushDispatcher $push,
    ): int {
        $now = $this->resolveNow();
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun) {
            // The heartbeat the admin dashboard watches. Written first, so a
            // crash midway still shows the scheduler as alive — the alert is
            // for "nothing is running", not "something went wrong" (FR-074).
            Setting::write(Setting::SCHEDULER_LAST_RUN_AT, $now->toIso8601String());
        }

        $tally = [
            'users' => 0,
            'reviews_built' => 0,
            'daily_queued' => 0,
            'reminder_queued' => 0,
            'push_queued' => 0,
            'skipped' => [],
        ];

        foreach ($this->candidates() as $user) {
            $tally['users']++;

            $localDay = $days->localDayFor($user, $now);

            if ($days->isWithinSendWindow($user, $this->timeOf($user->daily_email_at), $now)) {
                $this->handleDaily($user, $localDay, $builder, $dispatcher, $dryRun, $tally);
            }

            if ($days->isWithinSendWindow($user, $this->timeOf($user->reminder_email_at), $now)) {
                $this->handleReminder($user, $localDay, $builder, $dispatcher, $dryRun, $tally);
            }

            if ($days->isWithinSendWindow($user, $this->pushTimeFor($user), $now)) {
                $this->handleNudge($user, $days, $now, $push, $dryRun, $tally);
            }
        }

        $this->report($tally, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $tally
     */
    private function handleDaily(
        User $user,
        \Carbon\CarbonImmutable $localDay,
        ReviewBuilder $builder,
        MailDispatcher $dispatcher,
        bool $dryRun,
        array &$tally,
    ): void {
        if (! $user->daily_email_enabled) {
            $this->note($tally, 'daily:unsubscribed');

            return;
        }

        if ($dryRun) {
            $this->note($tally, 'daily:dry-run');

            return;
        }

        $review = $builder->buildFor($user, $localDay);

        // No eligible highlights means no review and no email. Sending a
        // blank page would be worse than sending nothing (FR-037, US2-7).
        if ($review === null) {
            $this->note($tally, 'daily:nothing_to_send');

            return;
        }

        if ($review->wasRecentlyCreated) {
            $tally['reviews_built']++;
        }

        if ($dispatcher->queueDailyReview($user, $review, $localDay) !== null) {
            $tally['daily_queued']++;
        } else {
            $this->note($tally, 'daily:already_queued');
        }
    }

    /**
     * @param  array<string, mixed>  $tally
     */
    private function handleReminder(
        User $user,
        \Carbon\CarbonImmutable $localDay,
        ReviewBuilder $builder,
        MailDispatcher $dispatcher,
        bool $dryRun,
        array &$tally,
    ): void {
        if (! $user->reminder_email_enabled) {
            $this->note($tally, 'reminder:unsubscribed');

            return;
        }

        // A reminder is only ever about a review that already exists. It never
        // builds one — being nudged about something that was not there this
        // morning would be nonsense.
        $review = $builder->find($user, $localDay);

        if ($review === null) {
            $this->note($tally, 'reminder:no_review');

            return;
        }

        if ($review->status === Review::STATUS_COMPLETED) {
            $this->note($tally, 'reminder:already_completed');

            return;
        }

        if (! $dispatcher->reminderIsAllowed($user)) {
            $this->note($tally, 'reminder:rate_limited');

            return;
        }

        if ($dryRun) {
            $this->note($tally, 'reminder:dry-run');

            return;
        }

        if ($dispatcher->queueEveningReminder($user, $review, $localDay) !== null) {
            $tally['reminder_queued']++;
        } else {
            $this->note($tally, 'reminder:already_queued');
        }
    }

    /**
     * The browser nudge: an hour after the morning email, if the review is
     * still unfinished (FR-141).
     *
     * @param  array<string, mixed>  $tally
     */
    private function handleNudge(
        User $user,
        LocalDayResolver $days,
        Carbon $now,
        PushDispatcher $push,
        bool $dryRun,
        array &$tally,
    ): void {
        // The day the *email* belonged to, which is not always the day the
        // clock has reached. A reader whose email goes out at 23:30 is nudged
        // at 00:30, and the delivery has to be filed against the day they were
        // emailed — otherwise their local day could hold two nudges, one for
        // each side of midnight (contracts/console-and-jobs.md).
        $emailDay = $days->localDayFor($user, $now->copy()->subMinutes($this->nudgeDelay()));

        if ($dryRun) {
            // Ask the cheap questions anyway, so a dry run reports what would
            // actually happen rather than just that it was a dry run.
            $reason = $push->reasonNotToSend($user, $emailDay);

            $this->note($tally, 'push:'.($reason ?? 'dry-run'));

            return;
        }

        $reason = null;

        if ($push->queueReviewNudge($user, $emailDay, $reason) !== null) {
            $tally['push_queued']++;

            return;
        }

        $this->note($tally, 'push:'.($reason ?? 'skipped'));
    }

    /**
     * The reader's local wall-clock time for the nudge window: their email
     * time plus the configured wait, wrapping past midnight if it has to.
     */
    private function pushTimeFor(User $user): string
    {
        return Carbon::createFromFormat('H:i', $this->timeOf($user->daily_email_at))
            ->addMinutes($this->nudgeDelay())
            ->format('H:i');
    }

    private function nudgeDelay(): int
    {
        return (int) config('byagain.push.nudge_delay_minutes');
    }

    /**
     * Active, verified accounts only. An unverified address never receives the
     * ritual mail (FR-007).
     *
     * @return \Illuminate\Support\LazyCollection<int, User>
     */
    private function candidates(): \Illuminate\Support\LazyCollection
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotNull('email_verified_at')
            ->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))
            ->cursor();
    }

    private function resolveNow(): Carbon
    {
        $now = $this->option('now');

        return $now === null ? Carbon::now() : Carbon::parse((string) $now);
    }

    /**
     * The stored time is `HH:MM:SS`; the window comparison wants `HH:MM`.
     */
    private function timeOf(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }

    /**
     * @param  array<string, mixed>  $tally
     */
    private function note(array &$tally, string $reason): void
    {
        $tally['skipped'][$reason] = ($tally['skipped'][$reason] ?? 0) + 1;
    }

    /**
     * @param  array<string, mixed>  $tally
     */
    private function report(array $tally, bool $dryRun): void
    {
        if ($dryRun) {
            $this->comment('Dry run — nothing was written or queued.');
        }

        $this->line(sprintf(
            'users=%d reviews_built=%d daily_queued=%d reminder_queued=%d push_queued=%d',
            $tally['users'],
            $tally['reviews_built'],
            $tally['daily_queued'],
            $tally['reminder_queued'],
            $tally['push_queued'],
        ));

        foreach ($tally['skipped'] as $reason => $count) {
            $this->line("  skipped {$reason}: {$count}");
        }
    }
}
