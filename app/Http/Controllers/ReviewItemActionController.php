<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReviewItemActionRequest;
use App\Models\ReviewItem;
use App\Services\Review\ReviewItemActions;
use Illuminate\Http\JsonResponse;

/**
 * The review's only hot path: one small JSON endpoint, called once per card.
 *
 * The interface has already moved on by the time this runs (FR-041), so the
 * response exists to reconcile, not to unblock.
 */
final class ReviewItemActionController extends Controller
{
    public function __construct(private readonly ReviewItemActions $actions) {}

    public function __invoke(ReviewItemActionRequest $request, ReviewItem $item): JsonResponse
    {
        // A card arriving after the review was already completed means the
        // client's state has drifted; say so rather than quietly reopening it
        // (contracts/review-actions.md).
        if ($item->review->isCompleted() && ! $item->isActed()) {
            return response()->json(['message' => __('errors.server.body')], 409);
        }

        return response()->json(
            $this->actions->apply($request->user(), $item, $request->validated()),
        );
    }
}
