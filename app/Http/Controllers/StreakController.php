<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Streak\StreakService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StreakController extends Controller
{
    public function __construct(private readonly StreakService $streaks) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('streak.show', [
            'user' => $user,
            // Computed, not read off the user: a streak dies of neglect, and
            // nothing fires an update when someone simply stops (FR-057).
            'current' => $this->streaks->currentStreakFor($user),
            'longest' => $user->longest_streak,
            'calendar' => $this->streaks->calendar($user),
            // The streak at which the grid gains a row — null once it has
            // grown as far as a phone can show, so the page stops promising.
            'growsAt' => $this->streaks->nextGrowthAt($user),
        ]);
    }
}
