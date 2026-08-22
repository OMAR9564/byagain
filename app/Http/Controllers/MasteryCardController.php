<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasteryCardRequest;
use App\Http\Requests\UpdateMasteryCardRequest;
use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Services\Mastery\MasteryScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MasteryCardController extends Controller
{
    public function __construct(private readonly MasteryScheduler $scheduler) {}

    public function index(): View
    {
        $cards = MasteryCard::query()
            ->with('highlight.source')
            ->orderByRaw("FIELD(status, 'active', 'paused', 'retired')")
            ->orderBy('due_at')
            ->paginate(20);

        return view('mastery.index', ['cards' => $cards]);
    }

    /**
     * Build a card from a highlight the reader wants to actually hold on to,
     * rather than merely meet again (FR-044).
     */
    public function store(StoreMasteryCardRequest $request, Highlight $highlight): RedirectResponse
    {
        $data = $request->validated();

        $card = new MasteryCard;
        $card->fill([
            'highlight_id' => $highlight->id,
            'type' => $data['type'],
            'question' => $data['question'],
            'answer' => $this->answerFor($data),
        ]);

        // Left unscheduled on purpose: the first feedback sets the half-life
        // outright, so there is nothing meaningful to guess at now (FR-047).
        $card->save();

        return redirect()
            ->route('mastery.index')
            ->with('status', __('settings.saved'));
    }

    public function edit(MasteryCard $card): View
    {
        return view('mastery.edit', ['card' => $card->load('highlight.source')]);
    }

    public function update(UpdateMasteryCardRequest $request, MasteryCard $card): RedirectResponse
    {
        $data = $request->validated();

        $card->fill([
            'type' => $data['type'],
            'question' => $data['question'],
            'answer' => $this->answerFor($data),
        ]);

        $card->status = $data['status'];
        $card->save();

        return redirect()
            ->route('mastery.index')
            ->with('status', __('settings.saved'));
    }

    /**
     * Retire a card by hand. Hidden, never deleted (FR-050, FR-053).
     */
    public function retire(MasteryCard $card): RedirectResponse
    {
        $this->scheduler->retire($card);

        return back()->with('status', __('settings.saved'));
    }

    /**
     * A cloze carries its answer inside the question, so it is derived rather
     * than asked for twice — two fields that must agree are two fields that
     * will eventually disagree.
     *
     * @param  array<string, mixed>  $data
     */
    private function answerFor(array $data): string
    {
        if ($data['type'] !== MasteryCard::TYPE_CLOZE) {
            return (string) $data['answer'];
        }

        preg_match_all(StoreMasteryCardRequest::CLOZE_PATTERN, (string) $data['question'], $matches);

        return implode(', ', $matches[1]);
    }
}
