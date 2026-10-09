<?php

/*
|--------------------------------------------------------------------------
| Legal wording change for Cloudflare (2026-10-09)
|--------------------------------------------------------------------------
|
| softphoria.com is now delivered through Cloudflare's CDN, which can add
| Cloudflare Web Analytics (cookieless, not consent-gated) and, when its
| bot protection challenges a request, its own security cookies. The
| sentences that said every analytics tool waits for consent are narrowed
| to cookie-based tools; cookieless measurement is listed in the automatic
| "Analytics and marketing tools" section (AnalyticsIntegrations::cookieless()).
|
| Used by the 2026_10_09_200000 migration to update already-published pages,
| only where the old text is still word for word. resources/legal/*.html
| already contains the new text.
|
| slug => [[old, new], ...]
|
*/

return [
    'cookie-policy' => [
        [
            '<p>We keep cookies to a minimum. By default, the Website uses only the cookies it needs to work securely and to remember your cookie choices. Any analytics or marketing tools we use are listed under <strong>Analytics and marketing tools</strong> at the end of this policy. They stay switched off until you consent to them in the cookie banner, and you can withdraw that consent at any time.</p>',
            '<p>We keep cookies to a minimum. By default, the Website uses only the cookies it needs to work securely and to remember your cookie choices. Any analytics or marketing tools that use cookies are listed under <strong>Analytics and marketing tools</strong> at the end of this policy. They stay switched off until you consent to them in the cookie banner, and you can withdraw that consent at any time. Any cookieless measurement, which stores nothing on your device, is listed there too.</p>',
        ],
        [
            '<tr><td><code>cookie_consent</code></td><td>Remembers your cookie choices so we don\'t ask on every visit.</td><td>Strictly necessary (first-party)</td><td>180 days</td></tr>',
            '<tr><td><code>cookie_consent</code></td><td>Remembers your cookie choices so we don\'t ask on every visit.</td><td>Strictly necessary (first-party)</td><td>180 days</td></tr>'."\n"
            .'        <tr><td><code>__cf_bm</code>, <code>cf_clearance</code></td><td>Security: set by Cloudflare, which delivers the Website, only when its bot protection needs to check a request, so that genuine visitors are not challenged again.</td><td>Strictly necessary (set by Cloudflare)</td><td>30 minutes (<code>__cf_bm</code>); up to 1 day (<code>cf_clearance</code>)</td></tr>',
        ],
    ],
    'privacy-policy' => [
        [
            'We use analytics or advertising tools only with your consent; any in use are listed under <strong>Analytics and marketing tools</strong> at the end of this policy.',
            'We use analytics or advertising tools that set cookies only with your consent. These, and any cookieless measurement that stores nothing on your device, are listed under <strong>Analytics and marketing tools</strong> at the end of this policy.',
        ],
        [
            '<li><strong>Hosting and infrastructure providers</strong>, such as Amazon Web Services, which host the Website and our databases;</li>',
            '<li><strong>Hosting and infrastructure providers</strong>, such as Amazon Web Services, which host the Website and our databases, and Cloudflare, whose content delivery network delivers the Website and protects it against attacks (it handles every request, including your IP address);</li>',
        ],
        [
            '<li><strong>Analytics and advertising providers</strong>, only if you consent to the related cookies — see <strong>Analytics and marketing tools</strong> below;</li>',
            '<li><strong>Analytics and advertising providers</strong>: cookie-based tools only if you consent to the related cookies, and any cookieless measurement listed — see <strong>Analytics and marketing tools</strong> below;</li>',
        ],
    ],
];
