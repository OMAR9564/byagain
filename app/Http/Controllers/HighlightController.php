<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PreviewHighlightRequest;
use App\Http\Requests\StoreHighlightRequest;
use App\Http\Requests\UpdateHighlightRequest;
use App\Models\Highlight;
use App\Models\Source;
use App\Services\Content\ContentDeleter;
use App\Services\Content\HighlightWriter;
use App\Services\Content\PassageWithCards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HighlightController extends Controller
{
    public function __construct(
        private readonly HighlightWriter $highlightWriter,
        private readonly PassageWithCards $passages,
        private readonly ContentDeleter $deleter,
    ) {}

    public function create(Request $request): View
    {
        $sources = Source::query()
            ->where('is_archived', false)
            ->orderBy('title')
            ->get();

        // A ?source= that is not one of this reader's non-archived sources is
        // silently ignored (FR-215).
        $sourceId = $request->integer('source');
        $selectedSourceId = $sourceId > 0
            ? Source::query()->where('is_archived', false)->whereKey($sourceId)->value('id')
            : null;

        return view('editor.create', [
            'sources' => $sources,
            'selectedSourceId' => $selectedSourceId,
            'savedSource' => $sources->firstWhere('id', session('saved_source_id')),
        ]);
    }

    public function store(StoreHighlightRequest $request): RedirectResponse
    {
        [$highlight, $cardCount] = $this->passages->create($request->validated());

        // After saving, stay on the form with the chosen source selected and
        // show a link to the source's page (FR-212, FR-215). The user can add
        // another passage from the same source without re-selecting it.
        return redirect()
            ->route('highlights.create', ['source' => $highlight->source_id])
            ->with('status', $this->statusFor($cardCount))
            ->with('saved_source_id', $highlight->source_id);
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
            'html' => $this->highlightWriter->preview((string) $request->input('content_md', '')),
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
        $cardCount = $this->passages->update($highlight, $request->validated());

        return redirect()
            ->route('sources.show', $highlight->source_id)
            ->with('status', $this->statusFor($cardCount));
    }

    /**
     * Hide a highlight from future reviews. Not a delete (FR-013).
     */
    public function discard(Highlight $highlight): RedirectResponse
    {
        $this->highlightWriter->discard($highlight);

        return back()->with('status', __('settings.saved'));
    }

    public function favorite(Highlight $highlight): RedirectResponse
    {
        $this->highlightWriter->toggleFavorite($highlight);

        return back();
    }

    public function destroy(Highlight $highlight): RedirectResponse
    {
        $sourceId = $highlight->source_id;
        $this->deleter->deleteHighlight($highlight);

        return redirect()
            ->route('sources.show', $sourceId)
            ->with('status', __('library.highlight.deleted'));
    }

    private function statusFor(int $cardCount): string
    {
        return $cardCount > 0
            ? trans_choice('editor.saved_with_cards', $cardCount)
            : __('settings.saved');
    }
}
