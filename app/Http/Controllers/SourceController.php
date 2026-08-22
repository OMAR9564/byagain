<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SourceController extends Controller
{
    public function index(): View
    {
        // The ownership scope narrows this to the signed-in user; there is no
        // where-clause to forget here (Constitution art. III).
        $sources = Source::query()
            ->withCount(['highlights as active_highlights_count' => fn ($q) => $q->where('is_discarded', false)])
            ->orderBy('is_archived')
            ->orderBy('title')
            ->get();

        return view('library.index', ['sources' => $sources]);
    }

    public function create(): View
    {
        return view('library.sources.create');
    }

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        $source = Source::query()->create($request->validated());

        return redirect()
            ->route('sources.show', $source)
            ->with('status', __('settings.saved'));
    }

    public function show(Source $source): View
    {
        $highlights = $source->highlights()
            ->where('is_discarded', false)
            ->latest('id')
            ->paginate(20);

        return view('library.sources.show', [
            'source' => $source,
            'highlights' => $highlights,
        ]);
    }

    public function edit(Source $source): View
    {
        return view('library.sources.edit', ['source' => $source]);
    }

    public function update(UpdateSourceRequest $request, Source $source): RedirectResponse
    {
        $source->update($request->validated());

        return redirect()
            ->route('sources.show', $source)
            ->with('status', __('settings.saved'));
    }
}
