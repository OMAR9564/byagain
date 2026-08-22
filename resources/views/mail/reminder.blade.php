<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('mail.reminder.greeting') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#fbfaf8; color:#1c1a17;
             font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
             font-size:17px; line-height:1.6;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:520px; width:100%;">

                    {{-- No cards, no counting up what was missed, no
                         disappointment. Just how many are left and one way in
                         (FR-062). --}}
                    <tr>
                        <td style="font-size:19px; color:#1c1a17;">
                            {{ __('mail.reminder.greeting') }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:8px; font-size:17px; color:#5f5a52;">
                            {{ trans_choice('mail.reminder.remaining', $remaining, ['count' => $remaining]) }}
                            {{ __('mail.reminder.body') }}
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:24px 0 8px;">
                            <a href="{{ $reviewUrl }}"
                               style="display:inline-block; padding:14px 28px; border-radius:10px;
                                      background-color:#8a5a2b; color:#ffffff; text-decoration:none;
                                      font-size:17px; font-weight:500;">
                                {{ __('mail.reminder.cta') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding-top:20px; font-size:13px; color:#837c72;">
                            <a href="{{ $unsubscribeUrl }}" style="color:#837c72;">
                                {{ __('mail.footer.unsubscribe') }}
                            </a>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
