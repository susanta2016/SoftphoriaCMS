<?php

namespace App\Modules\Newsletter\Support;

/**
 * The layout a new template starts from: table-based, 600px, inline-safe
 * CSS with one mobile media query — the shape Gmail, Outlook (incl. the
 * Word engine via the MSO conditional) and Apple Mail all render
 * consistently. Admins edit it from here rather than from a blank page.
 */
final class StarterTemplate
{
    public static function html(): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html lang="en" xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="x-apple-disable-message-reformatting">
                <title>{{ campaign_title }}</title>
                <!--[if mso]><style>table,td{font-family:Arial,sans-serif !important;}</style><![endif]-->
            </head>
            <body class="nl-body">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="nl-wrapper">
                    <tr>
                        <td align="center" class="nl-outer">
                            <!--[if mso]><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"><tr><td><![endif]-->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="nl-container">
                                <tr>
                                    <td class="nl-header">{{ site_name }}</td>
                                </tr>
                                <tr>
                                    <td class="nl-content">
                                        <h1>{{ campaign_title }}</h1>
                                        <p>Hello {{ subscriber.first_name | "there" }},</p>
                                        {{ campaign_content }}
                                        {{ campaign.cta_button }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="nl-footer">{{ footer }}</td>
                                </tr>
                            </table>
                            <!--[if mso]></td></tr></table><![endif]-->
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
    }

    public static function css(): string
    {
        return <<<'CSS'
            body, .nl-body { margin: 0; padding: 0; background-color: #f3f4f6; -webkit-text-size-adjust: 100%; }
            .nl-wrapper { background-color: #f3f4f6; }
            .nl-outer { padding: 24px 12px; }
            .nl-container { max-width: 600px; background-color: #ffffff; border-radius: 8px; }
            .nl-header { padding: 24px 32px; font-family: Arial, Helvetica, sans-serif; font-size: 20px; font-weight: bold; color: #111827; border-bottom: 1px solid #e5e7eb; }
            .nl-content { padding: 32px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 24px; color: #374151; }
            .nl-content h1 { margin: 0 0 16px 0; font-size: 26px; line-height: 32px; color: #111827; }
            .nl-content p { margin: 0 0 16px 0; }
            .nl-content a { color: #2563eb; }
            .nl-content img { max-width: 100%; height: auto; }
            .nl-footer { padding: 24px 32px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; color: #6b7280; text-align: center; border-top: 1px solid #e5e7eb; }
            .nl-footer a { color: #6b7280; }
            @media only screen and (max-width: 620px) {
                .nl-outer { padding: 0 !important; }
                .nl-container { border-radius: 0 !important; }
                .nl-header, .nl-content, .nl-footer { padding-left: 20px !important; padding-right: 20px !important; }
                .nl-content h1 { font-size: 22px !important; line-height: 28px !important; }
            }
            CSS;
    }
}
