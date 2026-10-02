@props([
    'sources',
    'action',
    'highlight' => null,
    'method' => 'POST',
    'selectedSourceId' => null,
])

{{-- The editor. Built for one hand: the passage field is the first thing under
     the thumb, formatting sits directly above the keyboard rather than in a
     toolbar at the top of the screen, and the draft is kept locally so a
     dropped connection never costs anything typed (FR-020, FR-021). --}}
<form
    method="POST"
    action="{{ $action }}"
    class="flex flex-col gap-5"
    data-editor
    data-draft-key="{{ $highlight?->id ?? 'new' }}"
    data-preview-url="{{ route('highlights.preview') }}"
    data-preview-pending="{{ __('editor.preview.pending') }}"
    data-preview-failed="{{ __('editor.preview.failed') }}"
    data-preview-empty="{{ __('editor.preview.empty') }}"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="flex flex-col gap-1.5">
        <label for="source_id" class="text-sm font-medium" style="color: var(--color-ink);">
            {{ __('library.source.title') }}
        </label>

        <select
            id="source_id"
            name="source_id"
            class="min-h-11 w-full rounded-lg px-3"
            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
            required
        >
            @foreach ($sources as $source)
                {{-- Cast old() to int: it returns a string from the session, and
                     strict comparison will never match the integer $source->id.
                     Without this cast, a validation error loses the source
                     selection (FR-216). --}}
                <option value="{{ $source->id }}" @selected((int) old('source_id', $highlight?->source_id ?? $selectedSourceId) === $source->id)>
                    {{ $source->title }}
                </option>
            @endforeach
        </select>

        @error('source_id')
            <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <div class="flex items-center gap-1" role="tablist">
            <button type="button" role="tab" aria-selected="true" data-editor-tab="write"
                    class="min-h-11 rounded-lg px-3 text-sm font-medium">
                {{ __('editor.tab.write') }}
            </button>
            <button type="button" role="tab" aria-selected="false" data-editor-tab="preview"
                    class="min-h-11 rounded-lg px-3 text-sm font-medium">
                {{ __('editor.tab.preview') }}
            </button>

            <span class="ml-auto text-xs" style="color: var(--color-ink-subtle);" data-editor-draft-status></span>
        </div>

        <textarea
            id="content_md"
            name="content_md"
            rows="12"
            required
            autofocus
            data-editor-input
            placeholder="{{ __('editor.placeholder') }}"
            class="w-full rounded-lg p-3.5"
            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong); line-height: var(--leading-relaxed);"
        >{{ old('content_md', $highlight?->content_md) }}</textarea>

        {{-- The preview shows the passage exactly as a review card will: same
             renderer, same purifier, same styles. The HTML is produced by the
             server at /highlights/preview and is the very value that would be
             stored in `content_html` — there is no markdown renderer in the
             browser, deliberately, because a second one would be a second way
             for unpurified markup to reach the page. --}}
        <div
            data-editor-preview
            hidden
            class="highlight-content w-full rounded-lg p-3.5"
            style="background-color: var(--color-surface-sunken); border: 1px solid var(--color-border);"
        >
            <div class="highlight-body" data-editor-preview-body></div>
        </div>

        @error('content_md')
            <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
        @enderror

        {{-- Formatting keys sit here, immediately above the on-screen
             keyboard, so the thumb never travels. --}}
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'bold' => '**',
                'italic' => '*',
                'quote' => '> ',
                'list' => '- ',
                'code' => '`',
                'heading' => '## ',
            ] as $name => $token)
                <button
                    type="button"
                    data-editor-format="{{ $token }}"
                    class="min-h-11 min-w-11 rounded-lg px-3 text-sm"
                    style="background-color: var(--color-surface); color: var(--color-ink-muted); border: 1px solid var(--color-border);"
                >
                    {{ __('editor.format.' . $name) }}
                </button>
            @endforeach
        </div>
    </div>

    <x-field name="location" :label="__('library.highlight.location')" :value="$highlight?->location" />

    <div class="flex flex-col gap-1.5">
        <label for="note" class="text-sm font-medium" style="color: var(--color-ink);">
            {{ __('library.highlight.note') }}
        </label>

        <textarea
            id="note"
            name="note"
            rows="3"
            placeholder="{{ __('editor.note_placeholder') }}"
            class="w-full rounded-lg p-3.5"
            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
        >{{ old('note', $highlight?->note) }}</textarea>
    </div>

    {{-- Inline card creation (FR-208). Cards section with existing cards listed
         on the edit page, and the ability to add more. --}}
    <div class="flex flex-col gap-3">
        <h2 class="text-sm font-medium" style="color: var(--color-ink);">
            {{ __('mastery.inline.heading') }}
        </h2>

        {{-- On the edit screen, list the passage's existing non-retired cards
             so the reader sees what already exists. --}}
        @if ($highlight?->masteryCards()->where('status', '!=', \App\Models\MasteryCard::STATUS_RETIRED)->exists())
            <div class="flex flex-col gap-2">
                @foreach ($highlight->masteryCards()->where('status', '!=', \App\Models\MasteryCard::STATUS_RETIRED)->get() as $card)
                    <div class="flex items-start justify-between gap-2 rounded-lg p-3" style="background-color: var(--color-surface-sunken);">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm break-words" style="color: var(--color-ink);">
                                {{ Str::limit($card->question, 100) }}
                            </p>
                        </div>
                        <x-button
                            :href="route('mastery.edit', $card)"
                            variant="ghost"
                            class="!px-2.5 shrink-0"
                        >
                            {{ __('actions.edit') }}
                        </x-button>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- New card rows. --}}
        <div data-cards-list class="flex flex-col gap-3">
            @foreach (old('cards', []) as $index => $card)
                <fieldset data-card-row class="flex flex-col gap-3 rounded-lg p-3" style="background-color: var(--color-surface-sunken); border: 1px solid var(--color-border);">
                    {{-- Card type (QA or Cloze). --}}
                    <div class="flex flex-col gap-2">
                        <legend class="text-sm font-medium" style="color: var(--color-ink);">
                            {{ __('mastery.card.type') }}
                        </legend>

                        <label class="flex items-center gap-2.5" style="color: var(--color-ink);">
                            <input
                                type="radio"
                                name="cards[{{ $index }}][type]"
                                value="{{ \App\Models\MasteryCard::TYPE_QA }}"
                                class="h-4 w-4"
                                style="accent-color: var(--color-accent);"
                                @checked(old("cards.{$index}.type") === \App\Models\MasteryCard::TYPE_QA)
                            >
                            {{ __('mastery.card.type_qa') }}
                        </label>

                        <label class="flex items-center gap-2.5" style="color: var(--color-ink);">
                            <input
                                type="radio"
                                name="cards[{{ $index }}][type]"
                                value="{{ \App\Models\MasteryCard::TYPE_CLOZE }}"
                                class="h-4 w-4"
                                style="accent-color: var(--color-accent);"
                                @checked(old("cards.{$index}.type") === \App\Models\MasteryCard::TYPE_CLOZE)
                            >
                            {{ __('mastery.card.type_cloze') }}
                        </label>

                        <p class="text-sm" style="color: var(--color-ink-muted);">{{ __('mastery.card.cloze_hint') }}</p>
                    </div>

                    {{-- Question field. --}}
                    <div class="flex flex-col gap-1.5">
                        <label for="card-{{ $index }}-question" class="text-sm font-medium" style="color: var(--color-ink);">
                            {{ __('mastery.card.question') }}
                        </label>

                        <textarea
                            id="card-{{ $index }}-question"
                            name="cards[{{ $index }}][question]"
                            rows="3"
                            class="w-full rounded-lg p-2.5"
                            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
                        >{{ old("cards.{$index}.question") }}</textarea>

                        @error("cards.{$index}.question")
                            <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Answer field (only for QA type). --}}
                    <div class="flex flex-col gap-1.5">
                        <label for="card-{{ $index }}-answer" class="text-sm font-medium" style="color: var(--color-ink);">
                            {{ __('mastery.card.answer') }}
                        </label>

                        <textarea
                            id="card-{{ $index }}-answer"
                            name="cards[{{ $index }}][answer]"
                            rows="2"
                            class="w-full rounded-lg p-2.5"
                            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
                        >{{ old("cards.{$index}.answer") }}</textarea>

                        @error("cards.{$index}.answer")
                            <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Remove button. --}}
                    <button
                        type="button"
                        data-card-remove
                        class="self-start min-h-10 px-3 rounded-lg text-sm"
                        style="background-color: transparent; color: var(--color-critical); border: 1px solid var(--color-border);"
                    >
                        {{ __('mastery.inline.remove') }}
                    </button>
                </fieldset>
            @endforeach
        </div>

        {{-- Add card button. --}}
        <button
            type="button"
            data-cards-add
            class="self-start min-h-10 px-3 rounded-lg text-sm"
            style="background-color: var(--color-surface); color: var(--color-ink-muted); border: 1px solid var(--color-border);"
        >
            {{ __('mastery.inline.add') }}
        </button>

        {{-- Template for cloning new card rows. --}}
        <template data-card-template>
            <fieldset data-card-row class="flex flex-col gap-3 rounded-lg p-3" style="background-color: var(--color-surface-sunken); border: 1px solid var(--color-border);">
                <div class="flex flex-col gap-2">
                    <legend class="text-sm font-medium" style="color: var(--color-ink);">
                        {{ __('mastery.card.type') }}
                    </legend>

                    <label class="flex items-center gap-2.5" style="color: var(--color-ink);">
                        <input
                            type="radio"
                            name="cards[__INDEX__][type]"
                            value="{{ \App\Models\MasteryCard::TYPE_QA }}"
                            class="h-4 w-4"
                            style="accent-color: var(--color-accent);"
                            checked
                        >
                        {{ __('mastery.card.type_qa') }}
                    </label>

                    <label class="flex items-center gap-2.5" style="color: var(--color-ink);">
                        <input
                            type="radio"
                            name="cards[__INDEX__][type]"
                            value="{{ \App\Models\MasteryCard::TYPE_CLOZE }}"
                            class="h-4 w-4"
                            style="accent-color: var(--color-accent);"
                        >
                        {{ __('mastery.card.type_cloze') }}
                    </label>

                    <p class="text-sm" style="color: var(--color-ink-muted);">{{ __('mastery.card.cloze_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="card-__INDEX__-question" class="text-sm font-medium" style="color: var(--color-ink);">
                        {{ __('mastery.card.question') }}
                    </label>

                    <textarea
                        id="card-__INDEX__-question"
                        name="cards[__INDEX__][question]"
                        rows="3"
                        class="w-full rounded-lg p-2.5"
                        style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
                    ></textarea>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="card-__INDEX__-answer" class="text-sm font-medium" style="color: var(--color-ink);">
                        {{ __('mastery.card.answer') }}
                    </label>

                    <textarea
                        id="card-__INDEX__-answer"
                        name="cards[__INDEX__][answer]"
                        rows="2"
                        class="w-full rounded-lg p-2.5"
                        style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
                    ></textarea>
                </div>

                <button
                    type="button"
                    data-card-remove
                    class="self-start min-h-10 px-3 rounded-lg text-sm"
                    style="background-color: transparent; color: var(--color-critical); border: 1px solid var(--color-border);"
                >
                    {{ __('mastery.inline.remove') }}
                </button>
            </fieldset>
        </template>
    </div>

    <x-button type="submit">{{ __('actions.save') }}</x-button>
</form>

@vite('resources/js/editor.js')
