<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\EmailDelivery;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What an operator actually needs to know at a glance (FR-073, FR-074).
 *
 * The scheduler heartbeat is the one that matters. Everything in byagain hangs
 * off a single five-minute sweep; if cron stops, nothing throws and no page
 * breaks — reviews simply quietly stop being built and nobody gets their
 * morning email. Silence is the failure mode, so it has to be shown.
 */
final class OperationalStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->schedulerStat(),
            $this->accountsStat(),
            $this->completionStat(),
            $this->emailStat(),
            $this->queueStat(),
            $this->streakStat(),
        ];
    }

    private function schedulerStat(): Stat
    {
        $lastRun = Setting::read(Setting::SCHEDULER_LAST_RUN_AT);

        if (! is_string($lastRun)) {
            return Stat::make('Scheduler', 'Never run')
                ->description('byagain:dispatch-daily has not run')
                ->color('danger');
        }

        $at = Carbon::parse($lastRun);
        $minutes = (int) $at->diffInMinutes(Carbon::now());

        // Two missed ticks is the alarm: one late run is a slow machine, two
        // is cron being dead (SC-017).
        $stale = $minutes > ((int) config('byagain.mail.window_minutes') * 2);

        return Stat::make('Scheduler', $at->diffForHumans())
            ->description($stale ? 'Overdue — check the cron entry' : 'Healthy')
            ->color($stale ? 'danger' : 'success');
    }

    private function accountsStat(): Stat
    {
        $total = User::query()->count();
        $active = User::query()->where('status', User::STATUS_ACTIVE)->count();

        return Stat::make('Accounts', (string) $total)
            ->description("{$active} active")
            ->color('gray');
    }

    private function completionStat(): Stat
    {
        $since = Carbon::now()->subDays(7)->toDateString();

        $total = Review::query()->withoutGlobalScope('owned_by_user')
            ->where('review_date', '>=', $since)->count();

        $completed = Review::query()->withoutGlobalScope('owned_by_user')
            ->where('review_date', '>=', $since)
            ->where('status', Review::STATUS_COMPLETED)
            ->count();

        $rate = $total === 0 ? 0 : (int) round(($completed / $total) * 100);

        return Stat::make('Completion', "{$rate}%")
            ->description("{$completed} of {$total} reviews, last 7 days")
            ->color($rate >= 50 ? 'success' : 'warning');
    }

    private function emailStat(): Stat
    {
        $since = Carbon::now()->subDay();

        $sent = EmailDelivery::query()->withoutGlobalScope('owned_by_user')
            ->where('status', EmailDelivery::STATUS_SENT)
            ->where('created_at', '>=', $since)
            ->count();

        $failed = EmailDelivery::query()->withoutGlobalScope('owned_by_user')
            ->where('status', EmailDelivery::STATUS_FAILED)
            ->where('created_at', '>=', $since)
            ->count();

        return Stat::make('Email, 24h', (string) $sent)
            ->description($failed === 0 ? 'No failures' : "{$failed} failed")
            ->color($failed === 0 ? 'success' : 'danger');
    }

    private function queueStat(): Stat
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        return Stat::make('Queue', (string) $pending)
            ->description($failed === 0 ? 'No failed jobs' : "{$failed} failed jobs")
            ->color($failed === 0 ? 'gray' : 'danger');
    }

    private function streakStat(): Stat
    {
        $average = (float) User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->avg('current_streak');

        return Stat::make('Average streak', number_format($average, 1))
            ->description('Active accounts')
            ->color('gray');
    }
}
