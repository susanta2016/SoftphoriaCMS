<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<style>
    /* Client-supported embedded CSS (Gmail, Apple Mail, most modern clients).
       The inline styles below are the fallback for clients that strip
       <style> blocks (older Outlook) — every rule here is duplicated
       inline where it matters for legibility. */
    body, table, td { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    h1, h2, h3, h4, h5, h6 { margin: 0 0 0.6em 0; color: #16324f; font-weight: 600; line-height: 1.3; }
    p { margin: 0 0 1em 0; }
    a { color: #d99a3d; }
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
<body style="margin:0; padding:0; background-color:#f4f4f5; -webkit-text-size-adjust:100%; text-size-adjust:100%; overflow-x:hidden;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5;">
    <tr>
        <td align="center" style="padding:24px 16px;">
            <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:8px; overflow:hidden;">
                <tr>
                    <td class="email-padding" style="padding:32px 40px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#1f2937;">
                        {!! $content !!}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
