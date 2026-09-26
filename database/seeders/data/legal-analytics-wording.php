<?php

/*
|--------------------------------------------------------------------------
| Legal wording change for Analytics & Tracking (2026-09-26)
|--------------------------------------------------------------------------
|
| Sentences in resources/legal/*.html that said the site uses no analytics
| or advertising tools, and their replacements, which point to the
| automatically maintained "Analytics and marketing tools" section
| (resources/views/pages/partials/analytics-disclosure.blade.php).
|
| Used by the 2026_09_26_210000 migration to update already-published pages,
| only where the old sentence is still word for word.
|
| slug => [[old, new], ...]
|
*/

return [
    'cookie-policy' => [
        [
            '<p>We keep cookies to a minimum. The Website uses only the cookies it needs to work securely and to remember your cookie choices. We <strong>do not</strong> currently use analytics, tracking or advertising cookies, and we do not allow advertising networks to place cookies on the Website. If we ever introduce optional cookies, we will ask for your consent first through the cookie banner, and we will update this policy.</p>',
            '<p>We keep cookies to a minimum. By default, the Website uses only the cookies it needs to work securely and to remember your cookie choices. Any analytics or marketing tools we use are listed under <strong>Analytics and marketing tools</strong> at the end of this policy. They stay switched off until you consent to them in the cookie banner, and you can withdraw that consent at any time.</p>',
        ],
        [
            '<li><strong>Functionality</strong>, <strong>Tracking</strong>, and <strong>Targeting and advertising</strong>: optional categories we would use only with your consent. We do not currently use any cookies in these categories.</li>',
            '<li><strong>Functionality</strong>, <strong>Tracking</strong>, and <strong>Targeting and advertising</strong>: optional categories used only with your consent. The tools in each category, if any, are listed under <strong>Analytics and marketing tools</strong> below and in the cookie banner.</li>',
        ],
    ],
    'privacy-policy' => [
        [
            '<p>We do not sell your personal data, we do not use it for automated decision-making that has legal or similarly significant effects on you, and we do not use it to profile you for advertising.</p>',
            '<p>We do not sell your personal data, and we do not use it for automated decision-making that has legal or similarly significant effects on you. We use analytics or advertising tools only with your consent; any in use are listed under <strong>Analytics and marketing tools</strong> at the end of this policy.</p>',
        ],
        [
            '<li><strong>Embedded content providers</strong> (such as YouTube or Vimeo), only when you choose to play an embedded video;</li>',
            "<li><strong>Embedded content providers</strong> (such as YouTube or Vimeo), only when you choose to play an embedded video;</li>\n    <li><strong>Analytics and advertising providers</strong>, only if you consent to the related cookies — see <strong>Analytics and marketing tools</strong> below;</li>",
        ],
        [
            '<p><strong>If you live in a U.S. state with a consumer privacy law</strong> (such as California), you may have the right to know, access, correct and delete your personal information. We do not sell or "share" personal information for cross-context behavioural advertising, and we will not discriminate against you for exercising your rights.</p>',
            '<p><strong>If you live in a U.S. state with a consumer privacy law</strong> (such as California), you may have the right to know, access, correct and delete your personal information. We do not sell personal information. Any advertising tools that could involve "sharing" for cross-context behavioural advertising run only with your consent, which you can withdraw at any time using the cookie preferences icon on every page. We will not discriminate against you for exercising your rights.</p>',
        ],
    ],
];
