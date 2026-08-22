<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\Review\ReviewBuilder;
use App\Services\Streak\StreakService;
use App\Services\Time\LocalDayResolver;
use Illuminate\Http\JsonResponse;
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
     */
    public function show(Request $request): View
    {
        $user = $request->user();
        $review = $this->builder->buildFor($user);

        if ($review === null) {
            return view('review.empty');
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
     * Called by the client after the last card.
     *
     * Idempotent, and refuses when the client's idea of "finished" disagrees
     * with the server's (contracts/review-actions.md).
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $review = $this->builder->find($user, $this->days->localDayFor($user));

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
