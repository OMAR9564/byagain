{{-- The AGPL-3.0 obliges anyone running this over a network to offer its
     source to the people using it. A link in the footer is how that
     obligation is met (FR-093). --}}
<footer class="mt-12 px-4 pb-4 text-center text-xs" style="color: var(--color-ink-subtle);">
    <a href="{{ config('byagain.source_url') }}" rel="noopener noreferrer" target="_blank"
       style="color: var(--color-ink-subtle); text-decoration: underline; text-underline-offset: 2px;">
        {{ __('mail.footer.source') }}
    </a>
</footer>
