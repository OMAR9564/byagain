<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_settings_screen_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings')
            ->assertOk()
            ->assertSee(__('settings.review.title'));
    }

    #[Test]
    public function preferences_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings', $this->payload([
                'review_size' => 12,
                'mastery_ratio' => 30,
                'equal_source_weighting' => '1',
                'timezone' => 'Europe/Istanbul',
            ]))
            ->assertRedirect(route('settings.edit'));

        $user->refresh();

        $this->assertSame(12, $user->review_size);
        $this->assertSame(30, $user->mastery_ratio);
        $this->assertTrue($user->equal_source_weighting);
        $this->assertSame('Europe/Istanbul', $user->timezone);
    }

    #[Test]
    public function an_unchecked_box_turns_the_preference_off(): void
    {
        $user = User::factory()->create(['quality_filter_enabled' => true]);

        // Unchecked boxes are absent from the payload rather than false, which
        // is the classic way a toggle silently stops working.
        $this->actingAs($user)->patch('/settings', $this->payload([]))->assertRedirect();

        $this->assertFalse($user->refresh()->quality_filter_enabled);
    }

    #[Test]
    public function the_review_size_is_held_inside_its_configured_bounds(): void
    {
        $min = (int) config('byagain.review.min_size');
        $max = (int) config('byagain.review.max_size');

        $user = User::factory()->create(['review_size' => $min]);

        $this->actingAs($user)
            ->patch('/settings', $this->payload(['review_size' => $min - 1]))
            ->assertSessionHasErrors('review_size');

        $this->actingAs($user)
            ->patch('/settings', $this->payload(['review_size' => $max + 1]))
            ->assertSessionHasErrors('review_size');

        // Neither rejected value was written.
        $this->assertSame($min, $user->refresh()->review_size);

        $this->actingAs($user)
            ->patch('/settings', $this->payload(['review_size' => $max]))
            ->assertSessionHasNoErrors();

        $this->assertSame($max, $user->refresh()->review_size);
    }

    #[Test]
    public function a_made_up_timezone_is_rejected(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);

        // An offset is not a timezone: it does not know when the clocks
        // change, and the whole day boundary depends on that.
        $this->actingAs($user)
            ->patch('/settings', $this->payload(['timezone' => 'UTC+3']))
            ->assertSessionHasErrors('timezone');

        $this->assertSame('UTC', $user->refresh()->timezone);
    }

    #[Test]
    public function a_malformed_send_time_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings', $this->payload(['daily_email_at' => 'half past eight']))
            ->assertSessionHasErrors('daily_email_at');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides): array
    {
        return array_merge([
            'review_size' => 8,
            'mastery_ratio' => 50,
            'timezone' => 'UTC',
            'daily_email_at' => '08:00',
            'reminder_email_at' => '20:00',
        ], $overrides);
    }
}
