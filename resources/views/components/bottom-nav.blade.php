@php
    // Four destinations for the iOS bottom tab bar. Add moves to the navbar "+" on tab roots.
    // Labels are single words on purpose (see lang/en/nav.php): two words wrap
    // at this width and leave one tab taller than the rest.
    //
    // Entries are filtered by Route::has() so the shell stays renderable while
    // the app is still being built out, rather than fataling on a route that
    // does not exist yet.
    $items = collect([
        [
            'route' => 'review.show',
            'label' => __('nav.review'),
            // The mark's own three-line motif, so the tab you live in is the
            // same shape as the icon on the home screen.
            'paths' => ['M4 6.5h16', 'M4 12h13', 'M4 17.5h9'],
        ],
        [
            'route' => 'library.index',
            'label' => __('nav.library'),
            'paths' => ['M5 4.5h14v15H6.8A1.8 1.8 0 0 1 5 17.7z', 'M5 16.2h14'],
        ],
        [
            'route' => 'mix.show',
            'label' => __('nav.mix'),
            'paths' => ['M4 12c0 4.418 3.6 8 8 8s8-3.582 8-8-3.6-8-8-8-8 3.582-8 8z', 'M11.5 8v1M12.5 11h1v1'],
        ],
        [
            'route' => 'streak.show',
            'label' => __('nav.streak'),
            'paths' => ['M12 3.5c3 3.4 5 5.6 5 8.4a5 5 0 0 1-10 0c0-2.8 2-5 5-8.4z', 'M12 14.6c1.1 0 1.9-.7 1.9-1.7 0-.9-.7-1.6-1.9-2.9-1.2 1.3-1.9 2-1.9 2.9 0 1 .8 1.7 1.9 1.7z'],
        ],
    ])->filter(fn (array $item): bool => Route::has($item['route']));
@endphp

@if ($items->isNotEmpty())
    {{-- Pinning and safe-area padding live in app.css against
         `[data-bottom-nav]`, so the space the bar takes and the space the page
         leaves for it are written once (issue #2, R-202). --}}
    <nav
        data-bottom-nav
        aria-label="{{ __('nav.label') }}"
        class="fixed inset-x-0 bottom-0"
        style="z-index: var(--z-bottom-nav); background-color: var(--color-bar); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); border-top: 0.5px solid var(--color-separator);"
    >
        <ul class="mx-auto flex max-w-md items-stretch">
            @foreach ($items as $item)
                @php $active = request()->routeIs($item['route']); @endphp

                {{-- min-w-0 so a long label truncates inside its own tab
                     instead of widening it at a neighbour's expense. --}}
                <li class="min-w-0 flex-1">
                    <a
                        href="{{ route($item['route']) }}"
                        class="flex h-full flex-col items-center justify-center gap-0.5 px-1 pt-1"
                        style="min-height: var(--size-bottom-nav); color: {{ $active ? 'var(--color-accent)' : 'var(--color-tab-inactive)' }};"
                        @if ($active) aria-current="page" @endif
                    >
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="{{ $active ? '2.1' : '1.7' }}" stroke-linecap="round" stroke-linejoin="round"
                             style="width: 26px; height: 26px;">
                            @foreach ($item['paths'] as $path)
                                <path d="{{ $path }}" />
                            @endforeach
                        </svg>

                        <span class="w-full truncate text-center leading-none whitespace-nowrap"
                              style="font-size: 11px; font-weight: 500;">
                            {{ $item['label'] }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
