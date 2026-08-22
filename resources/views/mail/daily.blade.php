{{--
    Email HTML, which is not web HTML. Tables for layout, inline styles only,
    one column at 320px, and no external stylesheet — every mail client
    disagrees about everything else (FR-066).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ __('mail.daily.greeting') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#fbfaf8; color:#1c1a17;
             font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
             font-size:17px; line-height:1.6;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#fbfaf8;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:520px; width:100%;">

                    <tr>
                        <td style="padding-bottom:16px; font-size:15px; color:#5f5a52;">
                            {{ __('mail.daily.greeting') }}
                        </td>
                    </tr>

                    @foreach ($items as $item)
                        <tr>
                            <td style="padding-bottom:12px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                       style="background-color:#ffffff; border:1px solid #e3dfd8; border-radius:12px;">
                                    <tr>
                                        <td style="padding:18px;">
                                            @if ($item->item_type === \App\Models\ReviewItem::TYPE_MASTERY)
                                                <div style="font-size:13px; color:#837c72; padding-bottom:8px;">
                                                    {{ $item->masteryCard?->highlight?->source?->title }}
                                                </div>

                                                {{-- Only the question. The answer opens in the app,
                                                     or the card teaches nothing (contracts/mail.md). --}}
                                                <div style="font-size:17px; color:#1c1a17;">
                                                    {{ $item->masteryCard?->question }}
                                                </div>
                                            @else
                                                <div style="font-size:13px; color:#837c72; padding-bottom:8px;">
                                                    {{ $item->highlight?->source?->title }}
                                                    @if ($item->highlight?->location !== null)
                                                        · {{ $item->highlight->location }}
                                                    @endif
                                                </div>

                                                {{-- purified: MarkdownRenderer --}}
                                                {{-- Same guarantee as on the web: content_html has
                                                     one writer, and it purified this. --}}
                                                <div style="font-size:17px; color:#1c1a17; overflow:hidden;">
                                                    {!! $item->highlight?->content_html !!}
                                                </div>

                                                @if ($item->highlight?->note !== null)
                                                    <div style="margin-top:12px; padding-left:10px;
                                                                border-left:2px solid #cfc9bf;
                                                                font-size:15px; color:#5f5a52; font-style:italic;">
                                                        {{ $item->highlight->note }}
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endforeach

                    {{-- One call to action, and only one (FR-060). --}}
                    <tr>
                        <td align="center" style="padding:20px 0 8px;">
                            <a href="{{ $reviewUrl }}"
                               style="display:inline-block; padding:14px 28px; border-radius:10px;
                                      background-color:#8a5a2b; color:#ffffff; text-decoration:none;
                                      font-size:17px; font-weight:500;">
                                {{ __('mail.daily.cta') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding-top:20px; font-size:13px; color:#837c72;">
                            <a href="{{ $unsubscribeUrl }}" style="color:#837c72;">
                                {{ __('mail.footer.unsubscribe') }}
                            </a>
                            &nbsp;·&nbsp;
                            <a href="{{ route('settings.edit') }}" style="color:#837c72;">
                                {{ __('mail.footer.settings') }}
                            </a>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
