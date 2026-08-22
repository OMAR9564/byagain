{{ __('mail.reminder.greeting') }}

{{ trans_choice('mail.reminder.remaining', $remaining, ['count' => $remaining]) }} {{ __('mail.reminder.body') }}

{{ __('mail.reminder.cta') }}: {{ $reviewUrl }}

{{ __('mail.footer.unsubscribe') }}: {{ $unsubscribeUrl }}
