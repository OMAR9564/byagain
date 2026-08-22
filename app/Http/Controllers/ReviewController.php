<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use App\Services\Streak\StreakService;
use App\Services\Time\LocalDayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewBuilder $builder,
        private readonly LocalDayResolver $days,
        private readonly StreakService $streaks,
    ) {}

    /**
     * Today's review, generated on first visit if the pipeline has not built
     * it already (FR-025).
     *
     * Once the day's quota is finished this screen stops offering more. The
     * ritual is meant to end — a screen that refills itself is a feed, and
     * the whole point of the product is that you can be done.
     */
    public function show(Request $request): View
    {
        $user = $request->user();
        $day = $this->days->localDayFor($user);

        if ($this->builder->buildFor($user, $day) === null) {
            return view('review.empty');
        }

        $review = $this->builder->latestFor($user, $day);

        if ($review !== null && $review->isCompleted()) {
            // Still inside what the reader asked for: open the next round
            // without making them ask for it.
            $review = $this->builder->roundsToday($user, $day) < $user->daily_review_limit
                ? $this->builder->buildNextRound($user, $day)
                : null;
        }

        if ($review === null || $review->isCompleted()) {
            return view('review.done', $this->doneState($user, $day));
        }

        if ($review->started_at === null) {
            $review->started_at = Carbon::now();
            $review->save();
        }

        return view('review.show', [
            'review' => $review,
            // Resuming lands on the first card the user has not dealt with,
            // not back at the beginning (FR-040).
            'startIndex' => $review->items->search(fn ($item): bool => ! $item->isActed()) ?: 0,
        ]);
    }

    /**
     * One more round, because the reader asked twice.
     *
     * Deliberately a POST behind a button rather than something `/review`
     * does on its own: going past the day's own review should be a decision,
     * not what happens if you open the app again.
     */
    public function again(Request $request): RedirectResponse
    {
        $user = $request->user();
        $day = $this->days->localDayFor($user);

        $review = $this->builder->buildNextRound($user, $day);

        if ($review === null) {
            return redirect()
                ->route('review.show')
                ->with('status', __('review.again.exhausted'));
        }

        return redirect()->route('review.show');
    }

    /**
     * What the "that is today" screen needs to know.
     *
     * @return array<string, mixed>
     */
    private function doneState(User $user, CarbonImmutable $day): array
    {
        $rounds = $this->builder->roundsToday($user, $day);

        return [
            'streak' => $this->streaks->currentStreakFor($user, $day),
            'rounds' => $rounds,
            'limit' => $user->daily_review_limit,
            // The reader may always insist, up to the point where insisting
            // stops being a request and starts being a loop.
            'canRepeat' => $rounds < (int) config('byagain.review.max_rounds_per_day'),
        ];
    }

    /**
     * Called by the client after the last card.
     *
     * Idempotent, and refuses when the client's idea of "finished" disagrees
     * with the server's (contracts/review-actions.md).
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        // The round the reader is actually in, which after an extra round is
        // not the one the email was built from.
        $review = $this->builder->latestFor($user, $this->days->localDayFor($user));

        if ($review === null) {
            abort(404);
        }

        if ($review->isCompleted()) {
            return response()->json($this->completionPayload($request, $review));
        }

        if ($review->items()->whereNull('acted_at')->exists()) {
            return response()->json([
                'message' => __('errors.server.body'),
                'remaining' => $review->items()->whereNull('acted_at')->count(),
            ], 409);
        }

        DB::transaction(function () use ($review, $user): void {
            $review->status = Review::STATUS_COMPLETED;
            $review->completed_at = Carbon::now();
            $review->save();

            $this->streaks->recordCompletion($user);
        });

        return response()->json($this->completionPayload($request, $review));
    }

    /**
     * @return array<string, mixed>
     */
    private function completionPayload(Request $request, Review $review): array
    {
        $user = $request->user()->refresh();

        return [
            'completed_at' => $review->completed_at?->toIso8601String(),
            'streak' => [
                'current' => $user->current_streak,
                'longest' => $user->longest_streak,
            ],
        ];
    }
}
