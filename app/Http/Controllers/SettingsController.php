<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'user' => $request->user(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // Everything here takes effect from the next review onwards. Today's
        // is already written and stays as it is (FR-026).
        $request->user()->update($request->validated());

        return redirect()
            ->route('settings.edit')
            ->with('status', __('settings.saved'));
    }
}
