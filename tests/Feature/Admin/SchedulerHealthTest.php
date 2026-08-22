<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Widgets\OperationalStatsWidget;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Everything in byagain hangs off one five-minute sweep. If cron stops,
 * nothing throws and no page breaks — reviews quietly stop being built and
 * nobody gets their morning email. Silence is the failure mode, so the
 * dashboard has to say so out loud (FR-074, SC-017).
 */
final class SchedulerHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_scheduler_that_has_never_run_is_reported(): void
    {
        $this->assertDashboardShows('Never run');
    }

    #[Test]
    public function a_recent_run_reads_as_healthy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 12:00', 'UTC'));

        Setting::write(Setting::SCHEDULER_LAST_RUN_AT, Carbon::now()->subMinutes(2)->toIso8601String());

        $this->assertDashboardShows('Healthy');
    }

    #[Test]
    public function two_missed_ticks_raise_the_alarm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 12:00', 'UTC'));

        $window = (int) config('byagain.mail.window_minutes');

        // One late run is a slow machine; two is cron being dead.
        Setting::write(
            Setting::SCHEDULER_LAST_RUN_AT,
            Carbon::now()->subMinutes(($window * 2) + 1)->toIso8601String(),
        );

        $this->assertDashboardShows('Overdue');
    }

    #[Test]
    public function running_the_sweep_clears_the_alarm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 12:00', 'UTC'));

        Setting::write(Setting::SCHEDULER_LAST_RUN_AT, Carbon::now()->subHour()->toIso8601String());

        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertDashboardShows('Healthy');
    }

    /**
     * The stats are a Livewire component and render lazily, so the dashboard's
     * initial HTML does not carry them. Driving the widget directly tests the
     * logic rather than Filament's rendering, which is the part that can
     * actually be wrong.
     */
    private function assertDashboardShows(string $text): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(OperationalStatsWidget::class)->assertSee($text);
    }
}
