<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

/*
|----------------------------------------------------------------------------
| Web routes
|----------------------------------------------------------------------------
|
| Paths and route names are English (spec.md "Dil" decision, contracts/
| routes.md).
|
| Every route behind `auth` is additionally protected by the BelongsToUser
| global scope, so reaching for another account's id produces a 404 rather
| than a 403 — a 403 would confirm the record exists (FR-010, SC-011).
|
| Routes are added as their controllers land, so this file never names a
| controller that does not exist yet.
|
*/

Route::middleware(['auth', 'ensure.active'])->group(function (): void {
    Route::view('/', 'home')->name('home');

    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});
