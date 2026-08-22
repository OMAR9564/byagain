{{ __('mail.daily.greeting') }}
@foreach ($items as $item)

---
@if ($item->item_type === \App\Models\ReviewItem::TYPE_MASTERY)
{{ $item->masteryCard?->highlight?->source?->title }}

{{ $item->masteryCard?->question }}
@else
{{ $item->highlight?->source?->title }}@if ($item->highlight?->location !== null) · {{ $item->highlight->location }}@endif


{{ $item->highlight?->content_text }}
@if ($item->highlight?->note !== null)

{{ $item->highlight->note }}
@endif
@endif
@endforeach

---

{{ __('mail.daily.cta') }}: {{ $reviewUrl }}

{{ __('mail.footer.unsubscribe') }}: {{ $unsubscribeUrl }}
{{ __('mail.footer.source') }}
