<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A believable library to develop against, and the 20.000 highlight account
 * the performance budget is measured on (SC-004).
 *
 *   php artisan db:seed --class=DemoSeeder
 *   BYAGAIN_DEMO_HIGHLIGHTS=20000 php artisan db:seed --class=DemoSeeder
 *
 * Rows go in through the query builder rather than the model layer: 20.000
 * models would make seeding slower than the thing it exists to measure, and
 * these fixtures never need the render pipeline.
 */
final class DemoSeeder extends Seeder
{
    private const int CHUNK = 500;

    public function run(): void
    {
        $total = (int) config('byagain.demo.highlight_count');

        $user = User::query()->updateOrCreate(
            ['email' => 'demo@byagain.test'],
            [
                'name' => 'Demo Reader',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
                'timezone' => 'Europe/Istanbul',
            ],
        );

        $this->command->info("Seeding {$total} highlights for {$user->email}…");

        $sources = $this->sources($user);
        $perSource = (int) ceil($total / count($sources));

        foreach ($sources as $source) {
            $this->seedHighlights($user, $source, min($perSource, $total));
            $total -= $perSource;

            if ($total <= 0) {
                break;
            }
        }

        $this->command->info('Done. Sign in as demo@byagain.test / password');
    }

    /**
     * A spread of frequencies, so the weighting is actually exercised rather
     * than every source sitting at `normal`.
     *
     * @return array<int, Source>
     */
    private function sources(User $user): array
    {
        $definitions = [
            ['Meditations', 'Marcus Aurelius', 'book', 'often'],
            ['Letters from a Stoic', 'Seneca', 'book', 'normal'],
            ['The Pragmatic Programmer', 'Hunt & Thomas', 'book', 'very_often'],
            ['Thinking, Fast and Slow', 'Daniel Kahneman', 'book', 'low'],
            ['An essay I liked', null, 'article', 'rare'],
            ['A course I half finished', null, 'course', 'never'],
        ];

        return array_map(function (array $definition) use ($user): Source {
            [$title, $author, $type, $frequency] = $definition;

            $source = Source::query()->updateOrCreate(
                ['user_id' => $user->id, 'title' => $title],
                ['author' => $author, 'type' => $type, 'frequency' => $frequency],
            );

            return $source;
        }, $definitions);
    }

    private function seedHighlights(User $user, Source $source, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $now = Carbon::now();
        $rows = [];

        foreach (range(1, $count) as $i) {
            $text = $this->passage($source->title, $i);

            $rows[] = [
                'user_id' => $user->id,
                'source_id' => $source->id,
                'content_md' => $text,
                'content_html' => '<p>'.e($text).'</p>',
                'content_text' => $text,
                'note' => $i % 7 === 0 ? 'Worth coming back to.' : null,
                'location' => 'p. '.($i * 3),
                'is_favorite' => $i % 11 === 0,
                'is_discarded' => false,
                'contains_code' => false,
                'char_count' => mb_strlen($text),
                'shown_count' => 0,
                'last_shown_at' => null,
                'created_at' => $now->copy()->subDays($i % 90),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('highlights')->insert($chunk);
        }

        $source->highlights_count = $source->highlights()->where('is_discarded', false)->count();
        $source->save();
    }

    private function passage(string $sourceTitle, int $index): string
    {
        return sprintf(
            'Passage %d from %s. You have power over your mind, not outside events; '
            .'realise this, and you will find strength.',
            $index,
            $sourceTitle,
        );
    }
}
