<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function pruning_never_touches_user_content(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(5)->create();

        // Old enough to be pruned if the rule applied to content — it does
        // not, and must not (Constitution art. III).
        Carbon::setTestNow(Carbon::now()->addYears(2));

        $this->artisan('byagain:prune')->assertSuccessful();

        $this->assertDatabaseCount('highlights', 5);
        $this->assertDatabaseCount('sources', 1);
    }

    #[Test]
    public function old_sent_deliveries_are_pruned_but_failures_are_kept(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $retention = (int) config('byagain.mail.delivery_retention_days');
        $old = Carbon::now()->subDays($retention + 5);

        $sent = EmailDelivery::factory()->for($user)->sent()->create(['dedupe_key' => 'daily:old-sent']);
        $failed = EmailDelivery::factory()->for($user)->create([
            'dedupe_key' => 'daily:old-failed',
            'status' => EmailDelivery::STATUS_FAILED,
            'error' => 'provider timeout',
        ]);

        DB::table('email_deliveries')->update(['created_at' => $old]);

        $this->artisan('byagain:prune')->assertSuccessful();

        $this->assertDatabaseMissing('email_deliveries', ['id' => $sent->id]);

        // An old failure is exactly the thing somebody will want to look at.
        $this->assertDatabaseHas('email_deliveries', ['id' => $failed->id]);
    }

    #[Test]
    public function a_prune_dry_run_removes_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        EmailDelivery::factory()->for($user)->sent()->create();
        DB::table('email_deliveries')->update(['created_at' => Carbon::now()->subYear()]);

        $this->artisan('byagain:prune --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('email_deliveries', 1);
    }

    #[Test]
    public function rerendering_rebuilds_html_from_the_markdown_without_touching_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'content_md' => 'A passage with **emphasis**.',
        ]);

        // Simulate output produced by an older renderer.
        $highlight->content_html = '<p>stale</p>';
        $highlight->save();

        $this->artisan('byagain:rerender-highlights')->assertSuccessful();

        $highlight->refresh();

        $this->assertStringContainsString('<strong>', $highlight->content_html);
        $this->assertSame('A passage with **emphasis**.', $highlight->content_md);
    }

    #[Test]
    public function a_rerender_dry_run_writes_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $highlight->content_html = '<p>stale</p>';
        $highlight->save();

        $this->artisan('byagain:rerender-highlights --dry-run')->assertSuccessful();

        $this->assertSame('<p>stale</p>', $highlight->refresh()->content_html);
    }

    #[Test]
    public function the_admin_role_is_granted_only_from_the_console(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->artisan('byagain:promote-admin me@example.com')->assertSuccessful();
        $this->assertTrue($user->refresh()->isAdmin());

        $this->artisan('byagain:promote-admin me@example.com --demote')->assertSuccessful();
        $this->assertFalse($user->refresh()->isAdmin());
    }

    #[Test]
    public function promoting_an_unknown_address_fails_loudly(): void
    {
        $this->artisan('byagain:promote-admin nobody@example.com')->assertFailed();
    }
}
