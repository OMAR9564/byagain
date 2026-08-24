<x-layouts.app :title="__('settings.title')">
    <x-slot:header>{{ __('settings.title') }}</x-slot:header>

    <form method="POST" action="{{ route('settings.update') }}" class="flex flex-col gap-8">
        @csrf
        @method('PATCH')

        <section class="flex flex-col gap-4">
            <h2 class="text-base font-semibold" style="color: var(--color-ink);">
                {{ __('settings.review.title') }}
            </h2>

            <x-field
                name="review_size"
                type="number"
                :label="__('settings.review.size')"
                :value="$user->review_size"
                :help="__('settings.review.size_help', ['min' => config('byagain.review.min_size'), 'max' => config('byagain.review.max_size')])"
                :min="config('byagain.review.min_size')"
                :max="config('byagain.review.max_size')"
                required
            />

            <x-field
                name="daily_review_limit"
                type="number"
                :label="__('settings.review.daily_limit')"
                :value="$user->daily_review_limit"
                :help="__('settings.review.daily_limit_help', ['max' => config('byagain.review.max_daily_limit')])"
                min="1"
                :max="config('byagain.review.max_daily_limit')"
                required
            />

            <x-field
                name="mastery_ratio"
                type="number"
                :label="__('settings.review.mastery_ratio')"
                :value="$user->mastery_ratio"
                min="0"
                max="100"
                required
            />

            <x-toggle
                name="quality_filter_enabled"
                :label="__('settings.review.quality_filter')"
                :help="__('settings.review.quality_filter_help', ['count' => config('byagain.sampling.quality_min_chars')])"
                :checked="$user->quality_filter_enabled"
            />

            <x-toggle
                name="equal_source_weighting"
                :label="__('settings.review.equal_source_weighting')"
                :help="__('settings.review.equal_source_weighting_help')"
                :checked="$user->equal_source_weighting"
            />
        </section>

        <section class="flex flex-col gap-4">
            <h2 class="text-base font-semibold" style="color: var(--color-ink);">
                {{ __('settings.email.title') }}
            </h2>

            <div class="flex flex-col gap-1.5">
                <label for="timezone" class="text-sm font-medium" style="color: var(--color-ink);">
                    {{ __('settings.email.timezone') }}
                </label>

                {{-- A real IANA identifier, not an offset: the 04:00 day
                     boundary and the send window both depend on knowing when
                     this reader's clocks change. --}}
                <select id="timezone" name="timezone" class="min-h-11 w-full rounded-lg px-3"
                        style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);">
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $user->timezone) === $timezone)>
                            {{ $timezone }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-toggle
                name="daily_email_enabled"
                :label="__('settings.email.daily_enabled')"
                :checked="$user->daily_email_enabled"
            />

            <x-field
                name="daily_email_at"
                type="time"
                :label="__('settings.email.daily_time')"
                :value="substr((string) $user->daily_email_at, 0, 5)"
                required
            />

            <x-toggle
                name="reminder_email_enabled"
                :label="__('settings.email.reminder_enabled')"
                :checked="$user->reminder_email_enabled"
            />

            <x-field
                name="reminder_email_at"
                type="time"
                :label="__('settings.email.reminder_time')"
                :value="substr((string) $user->reminder_email_at, 0, 5)"
                required
            />
        </section>

        {{-- A second channel next to the email, and independent of it: either
             can be off without the other (FR-151).

             The switch carries everything push.js needs, because no
             user-facing string may live in a JS file (Constitution). It is
             rendered disabled where the server has no VAPID keys; push.js
             disables it again where the browser cannot do this — most often
             iOS before the app is on the home screen (R-206, FR-149). --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-base font-semibold" style="color: var(--color-ink);">
                {{ __('push.settings.legend') }}
            </h2>

            <x-toggle
                name="push_enabled"
                :label="__('push.settings.enabled')"
                :help="__('push.settings.help')"
                :checked="$user->push_enabled"
                :disabled="! $pushConfigured"
                data-push-toggle
                data-csrf="{{ csrf_token() }}"
                data-vapid-key="{{ $vapidPublicKey }}"
                data-subscribe-url="{{ route('push.subscribe') }}"
                data-unsubscribe-url="{{ route('push.unsubscribe') }}"
                data-unsupported-text="{{ __('push.settings.unsupported') }}"
                data-denied-text="{{ __('push.settings.denied') }}"
                data-unconfigured-text="{{ __('push.settings.unconfigured') }}"
            />

            <p data-push-note class="text-sm" style="color: var(--color-ink-muted);"
               @if ($pushConfigured) hidden @endif>
                @unless ($pushConfigured)
                    {{ __('push.settings.unconfigured') }}
                @endunless
            </p>
        </section>

        <x-button type="submit">{{ __('actions.save') }}</x-button>
    </form>

    {{-- Only here. The review screen's budget must not pay for a module it
         never runs (SC-006, R-208). --}}
    @vite('resources/js/push.js')

    <section class="mt-10 border-t pt-6" style="border-color: var(--color-border);">
        <h2 class="text-base font-semibold" style="color: var(--color-ink);">
            {{ __('settings.account.title') }}
        </h2>

        <p class="mt-1 text-sm" style="color: var(--color-ink-muted);">
            {{ __('settings.account.delete_help') }}
        </p>

        <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 flex flex-col gap-4">
            @csrf
            @method('DELETE')

            <x-field
                name="password"
                type="password"
                :label="__('settings.account.delete_confirm')"
                autocomplete="current-password"
                required
            />

            <label class="flex items-center gap-2.5 text-sm" style="color: var(--color-ink);">
                <input type="checkbox" name="confirm" value="1" class="h-5 w-5 rounded"
                       style="accent-color: var(--color-critical);">
                {{ __('settings.account.delete') }}
            </label>

            <x-button type="submit" variant="danger">{{ __('settings.account.delete') }}</x-button>
        </form>
    </section>
</x-layouts.app>
