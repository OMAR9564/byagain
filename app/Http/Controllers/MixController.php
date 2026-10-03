<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MixCardActionRequest;
use App\Http\Requests\MixHighlightActionRequest;
use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Services\Practice\MixSampler;
use App\Services\Practice\PracticeActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MixController extends Controller
{
    public function __construct(
        private readonly MixSampler $sampler,
        private readonly PracticeActions $actions,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $items = $this->sampler->draw($user);

        return view('mix.show', [
            'items' => $items,
        ]);
    }

    public function highlight(MixHighlightActionRequest $request, Highlight $highlight): JsonResponse
    {
        $this->actions->apply($highlight->source, $highlight, $request->validated());

        return response()->json(['ok' => true]);
    }

    public function card(MixCardActionRequest $request, MasteryCard $card): JsonResponse
    {
        // Mix card actions validate the review-action shape but write nothing
        // to the mastery schedule. The card's half-life, last_reviewed_at, due_at,
        // and scheduling state must never change (SC-201).
        // This keeps Mix as a practice mode, not a review mode.

        return response()->json(['ok' => true]);
    }
}
