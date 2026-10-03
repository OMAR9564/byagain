@props(['source' => null])

<x-field name="title" :label="__('library.source.name')" :value="$source?->title" required autofocus />
<x-field name="author" :label="__('library.source.author')" :value="$source?->author" />

<div class="flex flex-col gap-1.5">
    <label for="type" class="px-1 text-sm font-medium" style="color: var(--color-ink);">
        {{ __('library.source.type') }}
    </label>

    <select id="type" name="type" class="ios-field ios-select">
        @foreach (['book', 'article', 'note', 'podcast', 'course', 'other'] as $type)
            <option value="{{ $type }}" @selected(old('type', $source?->type) === $type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
</div>

<fieldset class="ios-section" style="margin-top: 0;">
    <legend class="ios-section-header">
        {{ __('library.source.frequency') }}
    </legend>

    {{-- Tiers come from config so this control and the sampler can never
         disagree about what exists (Constitution art. V). --}}
    @php
        $current = old('frequency', $source?->frequency ?? config('byagain.sampling.default_source_frequency'));
    @endphp

    <div class="ios-group">
        @foreach (\App\Http\Requests\StoreSourceRequest::frequencies() as $frequency)
            <label class="ios-row ios-check-row ios-row-link">
                <span class="flex-1">{{ __('library.frequency.' . $frequency) }}</span>
                <input type="radio" name="frequency" value="{{ $frequency }}" @checked($current === $frequency)>
                <svg class="ios-check" aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 8.5 6 12.5 14 3.5"/>
                </svg>
            </label>
        @endforeach
    </div>

    @error('frequency')
        <p class="ios-section-footer" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</fieldset>
