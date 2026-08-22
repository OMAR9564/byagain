<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PreviewHighlightRequest;
use App\Http\Requests\StoreHighlightRequest;
use App\Http\Requests\UpdateHighlightRequest;
use App\Models\Highlight;
use App\Models\Source;
use App\Services\Content\HighlightWriter;
use Illuminate\Http\JsonResponse;
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

    /**
     * The editor's preview tab.
     *
     * Rendered on the server on purpose. A markdown renderer in the bundle
     * would be a second path to the page that skips the purifier, and it would
     * drift from this one the first time either changed — the reader would be
     * shown one thing and sent another.
     */
    public function preview(PreviewHighlightRequest $request): JsonResponse
    {
        return response()->json([
            // Empty in, empty out: the editor asks for a preview of whatever
            // is in the field, including nothing.
            'html' => $this->writer->preview((string) $request->input('content_md', '')),
        ]);
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
