{{--
    EMAIL-001 — the shared Softphoria email frame, rendered by App\Shared\Mail\BrandedEmailLayout.
    Email-client safe on purpose: table layout, inline styles on every structural cell
    (the style block below is only progressive enhancement for clients that keep it),
    no web fonts, no scripts. The body slot is admin-edited template content that has
    already been substituted; it is printed raw and never compiled by Blade.
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        .sp-content p { margin: 0 0 16px; }
        .sp-content a { color: #2563eb; }
        .sp-content a.button { color: #ffffff !important; text-decoration: none !important; }
        .sp-content strong { color: #07162f; }
        .sp-content h1, .sp-content h2 { margin: 0 0 16px; color: #07162f; font-weight: 700; }
        .sp-content h1 { font-size: 22px; line-height: 30px; }
        .sp-content h2 { font-size: 18px; line-height: 26px; }
        .sp-footer a { color: #93c5fd; }
        @media only screen and (max-width: 620px) {
            .sp-container { width: 100% !important; }
            .sp-outer { padding: 16px 8px !important; }
            .sp-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .sp-content h1 { font-size: 20px !important; line-height: 28px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eef2f8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef2f8;">
        <tr>
            <td align="center" class="sp-outer" style="padding:32px 16px;">
                <table role="presentation" class="sp-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
                    {{-- Header: accent rule, logo (or text wordmark), tagline --}}
                    <tr>
                        <td style="height:4px;line-height:4px;font-size:0;background-color:#2563eb;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" class="sp-pad" style="padding:32px 40px 24px;border-bottom:1px solid #e5eaf2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                            <a href="{{ $siteUrl }}" style="text-decoration:none;">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="48" style="display:block;height:48px;width:auto;max-width:240px;margin:0 auto;border:0;">
                                @else
                                    <span style="font-size:26px;line-height:32px;font-weight:800;letter-spacing:-0.5px;color:#07162f;">{{ $siteName }}</span>
                                @endif
                            </a>
                            {{-- The uploaded logo artwork already carries the tagline; only the text wordmark needs it spelled out. --}}
                            @if (! $logoUrl && $tagline)
                                <p style="margin:10px 0 0;font-size:13px;line-height:18px;color:#64748b;">{{ $tagline }}</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Template-specific content (fully admin-edited, including any heading) --}}
                    <tr>
                        <td class="sp-pad sp-content" style="padding:36px 40px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:26px;color:#334155;">
                            {!! $body !!}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="sp-pad sp-footer" style="padding:28px 40px;background-color:#07162f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;color:#cbd5e1;">
                            <p style="margin:0;font-size:15px;line-height:22px;font-weight:700;color:#ffffff;">{{ $siteName }}</p>
                            @if ($tagline)
                                <p style="margin:2px 0 0;color:#94a3b8;">{{ $tagline }}</p>
                            @endif
                            <p style="margin:16px 0 0;">
                                <a href="{{ $siteUrl }}" style="color:#93c5fd;text-decoration:none;">Website</a>
                                <span style="color:#475569;">&nbsp;&middot;&nbsp;</span>
                                <a href="{{ $contactUrl }}" style="color:#93c5fd;text-decoration:none;">Contact</a>
                                @if ($privacyUrl)
                                    <span style="color:#475569;">&nbsp;&middot;&nbsp;</span>
                                    <a href="{{ $privacyUrl }}" style="color:#93c5fd;text-decoration:none;">Privacy</a>
                                @endif
                            </p>
                            <p style="margin:16px 0 0;font-size:12px;color:#94a3b8;">&copy; {{ $year }} {{ $siteName }}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
