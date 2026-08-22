<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreHighlightRequest;
use App\Http\Requests\UpdateHighlightRequest;
use App\Models\Highlight;
use App\Models\Source;
use App\Services\Content\HighlightWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class HighlightController extends Controller
{
    public function __construct(private readonly HighlightWriter $writer) {}

    public function create(): View
    {
        $sources = Source::query()
            ->where('is_archived', false)
            ->orderBy('title')
            ->get();

        return view('editor.create', ['sources' => $sources]);
    }

    public function store(StoreHighlightRequest $request): RedirectResponse
    {
        // Rendering, cleaning and the derived columns all live in the writer,
        // so there is exactly one path by which content_html comes into being.
        $highlight = $this->writer->create($request->validated());

        return redirect()
            ->route('sources.show', $highlight->source_id)
            ->with('status', __('settings.saved'));
    }

    public function edit(Highlight $highlight): View
    {
        $sources = Source::query()->orderBy('title')->get();

        return view('editor.edit', [
            'highlight' => $highlight,
            'sources' => $sources,
        ]);
    }

    public function update(UpdateHighlightRequest $request, Highlight $highlight): RedirectResponse
    {
        $this->writer->update($highlight, $request->validated());

        return redirect()
            ->route('sources.show', $highlight->source_id)
            ->with('status', __('settings.saved'));
    }

    /**
     * Hide a highlight from future reviews. Not a delete (FR-013).
     */
    public function discard(Highlight $highlight): RedirectResponse
    {
        $this->writer->discard($highlight);

        return back()->with('status', __('settings.saved'));
    }

    public function favorite(Highlight $highlight): RedirectResponse
    {
        $this->writer->toggleFavorite($highlight);

        return back();
    }
}
