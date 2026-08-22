@props(['source' => null])

<x-field name="title" :label="__('library.source.title')" :value="$source?->title" required autofocus />
<x-field name="author" :label="__('library.source.author')" :value="$source?->author" />

<div class="flex flex-col gap-1.5">
    <label for="type" class="text-sm font-medium" style="color: var(--color-ink);">
        {{ __('library.source.title') }}
    </label>

    <select id="type" name="type" class="min-h-11 w-full rounded-lg px-3"
            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);">
        @foreach (['book', 'article', 'note', 'podcast', 'course', 'other'] as $type)
            <option value="{{ $type }}" @selected(old('type', $source?->type) === $type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
</div>

<fieldset class="flex flex-col gap-2">
    <legend class="mb-1 text-sm font-medium" style="color: var(--color-ink);">
        {{ __('library.source.frequency') }}
    </legend>

    {{-- Tiers come from config so this control and the sampler can never
         disagree about what exists (Constitution art. V). --}}
    @php
        $current = old('frequency', $source?->frequency ?? config('byagain.sampling.default_source_frequency'));
    @endphp

    @foreach (\App\Http\Requests\StoreSourceRequest::frequencies() as $frequency)
        <label class="flex min-h-11 items-center gap-2.5 text-base" style="color: var(--color-ink);">
            <input type="radio" name="frequency" value="{{ $frequency }}" class="h-5 w-5"
                   style="accent-color: var(--color-accent);" @checked($current === $frequency)>
            {{ __('library.frequency.' . $frequency) }}
        </label>
    @endforeach

    @error('frequency')
        <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</fieldset>
