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
use Illuminate\Http\Response;
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
     * Round 1 is the only round this endpoint will ever open. A finished day
     * stays finished however often it is reopened, and a further round comes
     * only from `again()` (FR-101, FR-103).
     *
     * It used to top the day up here whenever the reader's own limit had room
     * left, which meant leaving for the library and tapping back into Review
     * dealt a fresh hand — the ritual could not end, and the ritual ending is
     * the product (Constitution art. I).
     *
     * If the request carries X-Byagain-Prefetch: 1, this is a background
     * download and we do not mark the review as started. This lets the app
     * prefetch today's review for offline use without advancing the ritual
     * (FR-086, FR-087).
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $day = $this->days->localDayFor($user);
        $isPrefetch = $request->header('X-Byagain-Prefetch') === '1';

        if ($this->builder->buildFor($user, $day) === null) {
            return $this->withExpiryHeader(view('review.empty'), $user, $day);
        }

        $review = $this->builder->latestFor($user, $day);

        if ($review === null || $review->isCompleted()) {
            return $this->withExpiryHeader(
                view('review.done', $this->doneState($user, $day)),
                $user,
                $day,
            );
        }

        // Only mark as started if this is not a background prefetch (FR-086).
        if (! $isPrefetch && $review->started_at === null) {
            $review->started_at = Carbon::now();
            $review->save();
        }

        return $this->withExpiryHeader(
            view('review.show', [
                'review' => $review,
                // Resuming lands on the first card the user has not dealt with,
                // not back at the beginning (FR-040).
                'startIndex' => $review->items->search(fn ($item): bool => ! $item->isActed()) ?: 0,
            ]),
            $user,
            $day,
        );
    }

    /**
     * Wrap a view response with the X-Byagain-Expires header.
     *
     * The header value is the end of the user's local day in UTC, which is
     * when any cached version of this review should expire. The service
     * worker checks this header and never serves an expired cached review
     * offline.
     */
    private function withExpiryHeader(View $view, User $user, CarbonImmutable $day): Response
    {
        [$_, $end] = $this->days->windowForLocalDay($user, $day);

        // Format as RFC 3339 with Z suffix for UTC (RFC 3339 §5.6).
        return response($view)->header('X-Byagain-Expires', $end->format('Y-m-d\TH:i:s\Z'));
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
            // The day the reader asked for, and the ceiling on any day at all.
            // Both have to hold: the limit is what closes an ordinary day, the
            // constant is what stops "one more" becoming a loop (FR-104,
            // FR-108).
            'canRepeat' => $rounds < (int) config('byagain.review.max_rounds_per_day')
                && $rounds < $user->daily_review_limit,
            // Asked outright rather than inferred. While `show()` opened
            // rounds by itself, arriving here at all proved the day was out of
            // material; now nothing has been attempted, so nothing is implied
            // (contracts/review-completion.md).
            'hasMaterial' => $this->builder->hasMaterialFor($user, $day),
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
