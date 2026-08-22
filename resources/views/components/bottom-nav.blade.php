@php
    // Thumb-reach navigation. Five destinations at most — past that the
    // targets get too narrow to hit reliably on a 375px screen (FR-078).
    //
    // Entries are filtered by Route::has() so the shell stays renderable while
    // the app is still being built out, rather than fataling on a route that
    // does not exist yet.
    $items = collect([
        ['route' => 'review.show', 'label' => __('review.title'), 'icon' => 'M4 5h16M4 12h16M4 19h10'],
        ['route' => 'library.index', 'label' => __('library.title'), 'icon' => 'M4 4h6v16H4zM14 4h6v16h-6z'],
        ['route' => 'highlights.create', 'label' => __('editor.title'), 'icon' => 'M12 5v14M5 12h14'],
        ['route' => 'mastery.index', 'label' => __('mastery.title'), 'icon' => 'M5 7h14v10H5zM9 11h6'],
        ['route' => 'streak.show', 'label' => __('streak.title'), 'icon' => 'M12 3v4M5 12H3m18 0h-2M12 21v-4'],
    ])->filter(fn (array $item): bool => Route::has($item['route']));
@endphp

@if ($items->isNotEmpty())
    <nav
        aria-label="{{ __('actions.next') }}"
        class="fixed inset-x-0 bottom-0 border-t"
        style="z-index: var(--z-bottom-nav); background-color: var(--color-surface); border-color: var(--color-border); padding-bottom: env(safe-area-inset-bottom);"
    >
        <ul class="mx-auto flex max-w-md items-stretch justify-around">
            @foreach ($items as $item)
                @php $active = request()->routeIs($item['route']); @endphp

                <li class="flex-1">
                    <a
                        href="{{ route($item['route']) }}"
                        @class(['flex flex-col items-center justify-center gap-1 py-2 text-xs'])
                        style="min-height: var(--size-bottom-nav); color: {{ $active ? 'var(--color-accent)' : 'var(--color-ink-muted)' }};"
                        @if ($active) aria-current="page" @endif
                    >
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" stroke-linecap="round" class="h-6 w-6">
                            <path d="{{ $item['icon'] }}" />
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
