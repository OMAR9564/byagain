<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Highlight>
 */
final class HighlightFactory extends Factory
{
    protected $model = Highlight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'user_id' => User::factory(),
            'source_id' => Source::factory(),
            'content_md' => $text,
            // Close enough for a fixture. Anything that exercises the real
            // rendering path should go through MarkdownRenderer instead.
            'content_html' => '<p>'.e($text).'</p>',
            'content_text' => $text,
            'note' => null,
            'location' => null,
            'is_favorite' => false,
            'is_discarded' => false,
            'contains_code' => false,
            'char_count' => mb_strlen($text),
            'shown_count' => 0,
            'last_shown_at' => null,
        ];
    }

    public function discarded(): self
    {
        return $this->state(fn (): array => ['is_discarded' => true]);
    }

    public function shownDaysAgo(int $days): self
    {
        return $this->state(fn (): array => [
            'last_shown_at' => Carbon::now()->subDays($days),
            'shown_count' => 1,
        ]);
    }

    /** A highlight short enough to be caught by the quality filter (FR-031). */
    public function short(): self
    {
        return $this->state(function (): array {
            $text = 'Too short.';

            return [
                'content_md' => $text,
                'content_html' => '<p>'.e($text).'</p>',
                'content_text' => $text,
                'char_count' => mb_strlen($text),
            ];
        });
    }
}
