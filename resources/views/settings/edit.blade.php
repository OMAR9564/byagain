<x-layouts.app :title="__('settings.title')" :back="url()->previous() !== url()->current() ? url()->previous() : route('home')">
    <x-slot:header>{{ __('settings.title') }}</x-slot:header>

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PATCH')

        <section class="ios-section">
            <h2 class="ios-section-header">{{ __('settings.review.title') }}</h2>

            <div class="ios-group">
                <x-field
                    name="review_size"
                    type="number"
                    inline
                    inputmode="numeric"
                    :label="__('settings.review.size')"
                    :value="$user->review_size"
                    :min="config('byagain.review.min_size')"
                    :max="config('byagain.review.max_size')"
                    required
                />

                <x-field
                    name="daily_review_limit"
                    type="number"
                    inline
                    inputmode="numeric"
                    :label="__('settings.review.daily_limit')"
                    :value="$user->daily_review_limit"
                    min="1"
                    :max="config('byagain.review.max_daily_limit')"
                    required
                />

                <x-field
                    name="mastery_ratio"
                    type="number"
                    inline
                    inputmode="numeric"
                    :label="__('settings.review.mastery_ratio')"
                    :value="$user->mastery_ratio"
                    min="0"
                    max="100"
                    required
                />
            </div>

            <div class="ios-section-footer">
                <p>{{ __('settings.review.size_help', ['min' => config('byagain.review.min_size'), 'max' => config('byagain.review.max_size')]) }}</p>
                <p class="mt-1">{{ __('settings.review.daily_limit_help', ['max' => config('byagain.review.max_daily_limit')]) }}</p>
            </div>
        </section>

        <section class="ios-section">
            <div class="ios-group">
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
            </div>
        </section>

        <section class="ios-section">
            <h2 class="ios-section-header">{{ __('settings.email.title') }}</h2>

            <div class="ios-group">
                {{-- A real IANA identifier, not an offset: the 04:00 day
                     boundary and the send window both depend on knowing when
                     this reader's clocks change. --}}
                <label for="timezone" class="ios-row">
                    <span class="flex-1">{{ __('settings.email.timezone') }}</span>
                    <select id="timezone" name="timezone"
                            class="ios-input ios-input--trailing ios-select" style="max-width: 55%;">
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $user->timezone) === $timezone)>
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <x-toggle
                    name="daily_email_enabled"
                    :label="__('settings.email.daily_enabled')"
                    :checked="$user->daily_email_enabled"
                />

                <x-field
                    name="daily_email_at"
                    type="time"
                    inline
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
                    inline
                    :label="__('settings.email.reminder_time')"
                    :value="substr((string) $user->reminder_email_at, 0, 5)"
                    required
                />
            </div>
        </section>

        {{-- A second channel next to the email, and independent of it: either
             can be off without the other (FR-151).

             The switch carries everything push.js needs, because no
             user-facing string may live in a JS file (Constitution). It is
             rendered disabled where the server has no VAPID keys; push.js
             disables it again where the browser cannot do this — most often
             iOS before the app is on the home screen (R-206, FR-149). --}}
        <section class="ios-section">
            <h2 class="ios-section-header">{{ __('push.settings.legend') }}</h2>

            <div class="ios-group">
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
            </div>

            <p data-push-note class="ios-section-footer"
               @if ($pushConfigured) hidden @endif>
                @unless ($pushConfigured)
                    {{ __('push.settings.unconfigured') }}
                @endunless
            </p>
        </section>

        <x-button type="submit" class="mt-8 w-full">{{ __('actions.save') }}</x-button>
    </form>

    {{-- Only here. The review screen's budget must not pay for a module it
         never runs (SC-006, R-208). --}}
    @vite('resources/js/push.js')

    <form method="POST" action="{{ route('account.destroy') }}">
        @csrf
        @method('DELETE')

        <section class="ios-section">
            <h2 class="ios-section-header">{{ __('settings.account.title') }}</h2>

            <div class="ios-group">
                <x-field
                    name="password"
                    type="password"
                    inline
                    :label="__('auth.field.password')"
                    :placeholder="__('settings.account.password_placeholder')"
                    autocomplete="current-password"
                    required
                />

                <label for="confirm" class="ios-row">
                    <span class="flex-1">{{ __('settings.account.delete') }}</span>
                    <input id="confirm" type="checkbox" name="confirm" value="1" class="ios-switch">
                </label>
            </div>

            <p class="ios-section-footer">{{ __('settings.account.delete_confirm') }} {{ __('settings.account.delete_help') }}</p>
        </section>

        <x-button type="submit" variant="danger" class="mt-4 w-full">{{ __('settings.account.delete') }}</x-button>
    </form>
</x-layouts.app>
