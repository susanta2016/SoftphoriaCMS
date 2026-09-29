<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
    /* Client-supported embedded CSS (Gmail, Apple Mail, most modern clients).
       The inline styles below are the fallback for clients that strip
       <style> blocks (older Outlook) — every rule here is duplicated
       inline where it matters for legibility. */
    body, table, td { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    table { border-collapse: collapse; }
    img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
    h1, h2, h3, h4, h5, h6 { margin: 0 0 0.6em 0; color: #16324f; font-weight: 600; line-height: 1.3; }
    p { margin: 0 0 1em 0; }
    a { color: #b97d24; }
    .email-footer a { color: #f0c877; }
    @media only screen and (max-width: 600px) {
        .email-container { width: 100% !important; }
        .email-padding { padding-left: 20px !important; padding-right: 20px !important; }
    }
    /* On <body> alone, the CSS Overflow spec's root-propagation rule means
       some browsers still let the viewport (<html>) scroll on its own,
       independently of body — visible as a second, redundant horizontal
       scrollbar when this document sits inside a narrow iframe (Filament's
       Email Template preview). Setting it on both elements removes that
       ambiguity; the admin preview's own wrapping div is the only intended
       horizontal scroll mechanism (see EditEmailTemplate::renderPreviewBody()). */
    html, body { overflow-x: hidden; }
</style>
</head>
{{--
    The shared email frame (App\Shared\Mail\BrandedEmailLayout supplies the brand data):
    site logo header, the admin-authored content, and a navy footer with site links and
    copyright. Table layout with inline styles, no scripts or web fonts. $content is the
    already-substituted template body, printed raw and never compiled by Blade.
--}}
<body style="margin:0; padding:0; background-color:#f4f4f5; -webkit-text-size-adjust:100%; text-size-adjust:100%; overflow-x:hidden;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5;">
    <tr>
        <td align="center" style="padding:24px 16px;">
            <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:8px; overflow:hidden;">
                {{-- Header: gold rule, then the site logo (or its name as text) --}}
                <tr>
                    <td style="height:4px; line-height:4px; font-size:0; background-color:#d99a3d;">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center" class="email-padding" style="padding:28px 40px 24px; border-bottom:1px solid #ece7dd;">
                        <a href="{{ $siteUrl }}" style="text-decoration:none;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="56" style="display:block; height:56px; width:auto; max-width:260px; margin:0 auto; border:0;">
                            @else
                                <span style="font-family:Georgia,'Times New Roman',serif; font-size:24px; line-height:32px; font-weight:700; color:#16324f;">{{ $siteName }}</span>
                            @endif
                        </a>
                    </td>
                </tr>

                {{-- Template-specific content, exactly as authored in Admin, Email Templates --}}
                <tr>
                    <td class="email-padding" style="padding:32px 40px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#1f2937;">
                        {!! $content !!}
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td class="email-padding email-footer" style="padding:28px 40px; background-color:#16324f; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:20px; color:#d5dde6;">
                        <p style="margin:0; font-size:15px; line-height:22px; font-weight:700; color:#ffffff;">{{ $siteName }}</p>
                        <p style="margin:4px 0 0; color:#b8c4d1;">{{ $subheading }}</p>
                        <p style="margin:16px 0 0;">
                            <a href="{{ $siteUrl }}" style="color:#f0c877; text-decoration:none;">Website</a>
                            <span style="color:#5d7590;">&nbsp;&middot;&nbsp;</span>
                            <a href="{{ $contactUrl }}" style="color:#f0c877; text-decoration:none;">Contact</a>
                            @if ($privacyUrl)
                                <span style="color:#5d7590;">&nbsp;&middot;&nbsp;</span>
                                <a href="{{ $privacyUrl }}" style="color:#f0c877; text-decoration:none;">Privacy</a>
                            @endif
                        </p>
                        <p style="margin:16px 0 0; font-size:12px; color:#9fb0c2;">{{ $copyright }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
