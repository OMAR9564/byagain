<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Models\User;
use App\Services\Identity\Greeting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The line at the top of every screen.
 *
 * The part of the day comes from the reader's own clock, and for one account
 * their name is written differently every day. Neither is stored, so the only
 * thing that can go wrong is the arithmetic — which is what is tested here.
 */
final class GreetingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_part_of_the_day_follows_the_readers_own_clock(): void
    {
        // 06:00 UTC is breakfast in London and mid-afternoon in Tokyo. Two
        // readers opening the app at the same instant are not in the same
        // part of the day.
        Carbon::setTestNow(Carbon::parse('2026-08-22 06:00', 'UTC'));

        $greeting = app(Greeting::class);

        $london = new User(['name' => 'Ada', 'timezone' => 'Europe/London']);
        $tokyo = new User(['name' => 'Ada', 'timezone' => 'Asia/Tokyo']);

        $this->assertSame('greeting.morning', $greeting->saluteKey($london));
        $this->assertSame('greeting.afternoon', $greeting->saluteKey($tokyo));
    }

    #[Test]
    public function the_small_hours_are_not_called_morning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 03:00', 'UTC'));

        $this->assertSame(
            'greeting.night',
            app(Greeting::class)->saluteKey(new User(['name' => 'Ada', 'timezone' => 'UTC'])),
        );
    }

    #[Test]
    public function the_greeting_uses_a_first_name(): void
    {
        $this->assertSame(
            'Ada',
            app(Greeting::class)->name(new User(['name' => 'Ada Lovelace'])),
        );
    }

    #[Test]
    public function only_one_account_gets_a_line(): void
    {
        $greeting = app(Greeting::class);

        $this->assertNotNull($greeting->endearment($this->recipient()));
        $this->assertNull($greeting->endearment($this->reader(9)));
    }

    #[Test]
    public function it_follows_the_account_and_not_the_name(): void
    {
        $greeting = app(Greeting::class);

        // Renaming the account keeps the note. Matching on a name would have
        // lost it here, and handed it to the impostor below.
        $renamed = $this->recipient();
        $renamed->name = 'Someone Else';

        $this->assertNotNull($greeting->endearment($renamed));

        $impostor = $this->reader(9);
        $impostor->name = $this->recipient()->name;

        $this->assertNull($greeting->endearment($impostor));
    }

    #[Test]
    public function it_can_be_switched_off(): void
    {
        config(['byagain.endearment.user_id' => null]);

        $this->assertNull(app(Greeting::class)->endearment($this->recipient()));
    }

    #[Test]
    public function the_line_holds_for_a_day_and_turns_over_with_it(): void
    {
        $greeting = app(Greeting::class);
        $user = $this->recipient();

        $monday = CarbonImmutable::parse('2026-08-24');

        $this->assertSame(
            $greeting->endearment($user, $monday),
            $greeting->endearment($user, $monday),
        );

        $this->assertNotSame(
            $greeting->endearment($user, $monday),
            $greeting->endearment($user, $monday->addDay()),
        );
    }

    #[Test]
    public function every_line_in_the_rotation_comes_round(): void
    {
        $greeting = app(Greeting::class);
        $user = $this->recipient();

        /** @var array<int, string> $lines */
        $lines = config('byagain.endearment.lines');

        $day = CarbonImmutable::parse('2026-01-01');
        $seen = [];

        for ($offset = 0; $offset < count($lines); $offset++) {
            $seen[] = $greeting->endearment($user, $day->addDays($offset));
        }

        // A rotation that repeats before it has finished would mean the
        // arithmetic, not the list, decides how much variety there is.
        $this->assertCount(count($lines), array_unique($seen));
    }

    /**
     * The account the note is written for.
     */
    private function recipient(): User
    {
        return $this->reader((int) config('byagain.endearment.user_id'));
    }

    private function reader(int $id): User
    {
        $user = new User(['name' => 'Mila', 'timezone' => 'UTC']);
        $user->id = $id;

        return $user;
    }
}
