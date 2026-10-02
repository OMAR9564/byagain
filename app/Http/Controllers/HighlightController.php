<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PreviewHighlightRequest;
use App\Http\Requests\StoreHighlightRequest;
use App\Http\Requests\UpdateHighlightRequest;
use App\Models\Highlight;
use App\Models\Source;
use App\Services\Content\HighlightWriter;
use App\Services\Mastery\MasteryCardWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class HighlightController extends Controller
{
    public function __construct(
        private readonly HighlightWriter $highlightWriter,
        private readonly MasteryCardWriter $cardWriter,
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
        $data = $request->validated();
        $cardData = $data['cards'] ?? [];
        unset($data['cards']);

        // Wrap highlight creation and card creation in a transaction so a
        // failure leaves neither (FR-208).
        $highlight = DB::transaction(function () use ($data, $cardData) {
            // Rendering, cleaning and the derived columns all live in the
            // writer, so there is exactly one path by which content_html
            // comes into being.
            $highlight = $this->highlightWriter->create($data);

            // Create any inline cards that were added with the passage.
            $cardCount = 0;
            foreach ($cardData as $card) {
                $this->cardWriter->create($highlight, $card);
                $cardCount++;
            }

            // Store card count in session for status message.
            if ($cardCount > 0) {
                session(['created_card_count' => $cardCount]);
            }

            return $highlight;
        });

        // After saving, stay on the form with the chosen source selected and
        // show a link to the source's page (FR-212, FR-215). The user can add
        // another passage from the same source without re-selecting it.
        $statusMessage = __('settings.saved');
        if (session('created_card_count')) {
            $statusMessage = __('editor.saved_with_cards', [
                'count' => session('created_card_count'),
            ]);
            session()->forget('created_card_count');
        }

        return redirect()
            ->route('highlights.create', ['source' => $highlight->source_id])
            ->with('status', $statusMessage)
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
        $data = $request->validated();
        $cardData = $data['cards'] ?? [];
        unset($data['cards']);

        // Wrap highlight update and card creation in a transaction so a
        // failure leaves neither (FR-208).
        DB::transaction(function () use ($highlight, $data, $cardData): void {
            $this->highlightWriter->update($highlight, $data);

            // Create any new inline cards that were added during editing.
            $cardCount = 0;
            foreach ($cardData as $card) {
                $this->cardWriter->create($highlight, $card);
                $cardCount++;
            }

            // Store card count in session for status message.
            if ($cardCount > 0) {
                session(['created_card_count' => $cardCount]);
            }
        });

        $statusMessage = __('settings.saved');
        if (session('created_card_count')) {
            $statusMessage = __('editor.saved_with_cards', [
                'count' => session('created_card_count'),
            ]);
            session()->forget('created_card_count');
        }

        return redirect()
            ->route('sources.show', $highlight->source_id)
            ->with('status', $statusMessage);
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
}
