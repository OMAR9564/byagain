<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailWebhookController;
use App\Http\Controllers\MasteryCardController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewItemActionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\StreakController;
use App\Http\Controllers\StudyExportController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|----------------------------------------------------------------------------
| Web routes
|----------------------------------------------------------------------------
|
| Paths and route names are English (spec.md "Dil" decision, contracts/
| routes.md).
|
| Every route below is additionally protected by the BelongsToUser global
| scope, so reaching for another account's id produces a 404 rather than a
| 403 — a 403 would confirm the record exists (FR-010, SC-011).
|
| Routes are added as their controllers land, so this file never names a
| controller that does not exist yet.
|
*/

/*
 * Signed-out routes.
 *
 * `unsubscribe` is authorised by its signature rather than by a session:
 * someone who wants these emails to stop should not have to find their
 * password first, or "unsubscribe" becomes "mark as spam" (FR-065).
 */
Route::get('/unsubscribe/{user}/{type}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('unsubscribe');

Route::post('/webhooks/mail', MailWebhookController::class)->name('webhooks.mail');

Route::middleware(['auth', 'ensure.active'])->group(function (): void {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/review', [ReviewController::class, 'show'])->name('review.show');
    Route::post('/review/complete', [ReviewController::class, 'complete'])->name('review.complete');

    // Another round, on top of a day that is already finished. Throttled
    // because it is the one route here that mints work on demand.
    Route::post('/review/again', [ReviewController::class, 'again'])
        ->middleware('throttle:20,1')
        ->name('review.again');

    // The ritual's hot path. Rate limited per user rather than per IP: a
    // household behind one address must not throttle each other.
    Route::post('/review/items/{item}/action', ReviewItemActionController::class)
        ->middleware('throttle:120,1')
        ->name('review.item.action');

    Route::get('/library', [SourceController::class, 'index'])->name('library.index');
    Route::get('/library/sources/create', [SourceController::class, 'create'])->name('sources.create');
    Route::post('/library/sources', [SourceController::class, 'store'])->name('sources.store');
    Route::get('/library/sources/{source}', [SourceController::class, 'show'])->name('sources.show');
    Route::get('/library/sources/{source}/edit', [SourceController::class, 'edit'])->name('sources.edit');
    Route::patch('/library/sources/{source}', [SourceController::class, 'update'])->name('sources.update');

    // Practice one source without touching the day: no review record, no
    // streak recording, no mastery scheduling. Only explicit decisions
    // (discard, favorite, frequency) persist (FR-201, FR-206, R-305).
    Route::get('/library/sources/{source}/practice', [PracticeController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('practice.show');

    Route::post('/library/sources/{source}/practice/{highlight}', [PracticeController::class, 'action'])
        ->middleware('throttle:120,1')
        ->scopeBindings()
        ->name('practice.action');

    // Export a source's passages and cards as study text for an LLM. Throttled
    // because each request reads and renders the whole source (R-309).
    Route::get('/library/sources/{source}/export', [StudyExportController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('sources.export');

    Route::get('/library/sources/{source}/export/download', [StudyExportController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('sources.export.download');

    Route::get('/add', [HighlightController::class, 'create'])->name('highlights.create');

    // The preview tab. Rendered here rather than in the browser so there is
    // one renderer and one purifier; throttled because it is called while
    // someone is typing.
    Route::post('/highlights/preview', [HighlightController::class, 'preview'])
        ->middleware('throttle:60,1')
        ->name('highlights.preview');

    Route::post('/highlights', [HighlightController::class, 'store'])->name('highlights.store');
    Route::get('/highlights/{highlight}/edit', [HighlightController::class, 'edit'])->name('highlights.edit');
    Route::patch('/highlights/{highlight}', [HighlightController::class, 'update'])->name('highlights.update');
    Route::post('/highlights/{highlight}/discard', [HighlightController::class, 'discard'])->name('highlights.discard');
    Route::post('/highlights/{highlight}/favorite', [HighlightController::class, 'favorite'])->name('highlights.favorite');

    Route::post('/highlights/{highlight}/mastery', [MasteryCardController::class, 'store'])->name('mastery.store');
    Route::get('/mastery', [MasteryCardController::class, 'index'])->name('mastery.index');
    Route::get('/mastery/{card}/edit', [MasteryCardController::class, 'edit'])->name('mastery.edit');
    Route::patch('/mastery/{card}', [MasteryCardController::class, 'update'])->name('mastery.update');
    Route::post('/mastery/{card}/retire', [MasteryCardController::class, 'retire'])->name('mastery.retire');

    Route::get('/streak', [StreakController::class, 'show'])->name('streak.show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Registering and forgetting a browser, either side of its own permission
    // prompt. Throttled because a misbehaving client could otherwise call
    // these on a loop; turning notifications on or off is not something anyone
    // does twenty times a minute.
    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('push.subscribe');

    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('push.unsubscribe');

    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});
