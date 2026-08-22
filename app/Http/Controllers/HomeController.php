<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Source;
use App\Services\Review\ReviewBuilder;
use App\Services\Streak\StreakService;
use App\Services\Time\LocalDayResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The first screen. Its whole job is to answer "what now?" in one glance.
 */
final class HomeController extends Controller
{
    public function __construct(
        private readonly ReviewBuilder $builder,
        private readonly LocalDayResolver $days,
        private readonly StreakService $streaks,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Deliberately `find` rather than `buildFor`: opening the home screen
        // should not spend a sampling query building a review the reader may
        // not be about to start. /review builds it when they actually go
        // there, and the pipeline usually got there first anyway.
        $review = $this->builder->find($user, $this->days->localDayFor($user));

        return view('home', [
            'hasSources' => Source::query()->exists(),
            'review' => $review,
            'remaining' => $review === null ? 0 : $review->items->whereNull('acted_at')->count(),
            'streak' => $this->streaks->currentStreakFor($user),
        ]);
    }
}
