<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewItemActionController;
use App\Http\Controllers\SourceController;
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

Route::middleware(['auth', 'ensure.active'])->group(function (): void {
    Route::view('/', 'home')->name('home');

    Route::get('/review', [ReviewController::class, 'show'])->name('review.show');
    Route::post('/review/complete', [ReviewController::class, 'complete'])->name('review.complete');

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

    Route::get('/add', [HighlightController::class, 'create'])->name('highlights.create');
    Route::post('/highlights', [HighlightController::class, 'store'])->name('highlights.store');
    Route::get('/highlights/{highlight}/edit', [HighlightController::class, 'edit'])->name('highlights.edit');
    Route::patch('/highlights/{highlight}', [HighlightController::class, 'update'])->name('highlights.update');
    Route::post('/highlights/{highlight}/discard', [HighlightController::class, 'discard'])->name('highlights.discard');
    Route::post('/highlights/{highlight}/favorite', [HighlightController::class, 'favorite'])->name('highlights.favorite');

    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});
