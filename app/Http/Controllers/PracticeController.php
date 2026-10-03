<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PracticeActionRequest;
use App\Models\Highlight;
use App\Models\Source;
use App\Services\Practice\PracticeActions;
use App\Services\Practice\PracticeSampler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PracticeController extends Controller
{
    public function __construct(
        private readonly PracticeSampler $sampler,
        private readonly PracticeActions $actions,
    ) {}

    public function show(Request $request, Source $source): View|RedirectResponse
    {
        $user = $request->user();

        // Check if the source has active (non-discarded) passages. If not,
        // there's nothing to practice — even if cards exist, a practice set
        // must have at least one passage (FR-206).
        if ($this->sampler->countActivePassages($source) === 0) {
            return redirect()
                ->route('sources.show', $source)
                ->with('status', __('practice.practice.empty'));
        }

        $items = $this->sampler->draw($user, $source);

        return view('practice.show', [
            'source' => $source,
            'items' => $items,
        ]);
    }

    public function action(PracticeActionRequest $request, Source $source, Highlight $highlight): JsonResponse
    {
        $this->actions->apply($source, $highlight, $request->validated());

        return response()->json(['ok' => true]);
    }
}
