@props([
    'highlight',
    'collapsible' => true,
])

@php
    $limit = (int) config('byagain.content.collapse_after_chars');

    // Long passages are collapsed behind a control rather than dumped whole:
    // a 3.000-character page of prose on a 375px screen is a wall, and the
    // ritual is meant to take two minutes (FR-082).
    $collapsed = $collapsible && $highlight->char_count > $limit;
@endphp

<div
    {{ $attributes->merge(['class' => 'highlight-content']) }}
    @if ($collapsed) data-collapsible="true" @endif
>
    <div @class(['highlight-body', 'is-collapsed' => $collapsed])>
        {{-- purified: MarkdownRenderer --}}
        {{--
            The only unescaped echo in the application (Constitution art. III).
            It is safe because `content_html` has exactly one writer:
            MarkdownRenderer, which escapes raw HTML at the CommonMark stage
            and then purifies against an explicit whitelist. The column is
            absent from Highlight::$fillable so no request can reach it.

            If you are about to echo something else this way, don't.
        --}}
        {!! $highlight->content_html !!}
    </div>

    @if ($collapsed)
        <button
            type="button"
            class="mt-2 inline-flex min-h-11 items-center text-sm font-medium"
            style="color: var(--color-accent);"
            data-expand-highlight
            aria-expanded="false"
        >
            {{ __('actions.show_more') }}
        </button>
    @endif

    @if ($highlight->note !== null)
        <p class="mt-4 border-l-2 pl-3 text-base italic"
           style="border-color: var(--color-border-strong); color: var(--color-ink-muted);">
            {{ $highlight->note }}
        </p>
    @endif
</div>
