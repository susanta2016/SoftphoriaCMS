<?php

/*
|--------------------------------------------------------------------------
| Legal wording change: the actual host (2026-10-09)
|--------------------------------------------------------------------------
|
| softphoria.com is hosted by Namecheap (shared hosting: Website, database
| and email), not Amazon Web Services, which the Privacy Policy named.
|
| Used by the 2026_10_09_210000 migration to update the published page, only
| where the text is still word for word (after 2026_10_09_200000 has run).
| resources/legal/privacy-policy.html already contains the new text.
|
| slug => [[old, new], ...]
|
*/

return [
    'privacy-policy' => [
        [
            '<li><strong>Hosting and infrastructure providers</strong>, such as Amazon Web Services, which host the Website and our databases, and Cloudflare, whose content delivery network delivers the Website and protects it against attacks (it handles every request, including your IP address);</li>',
            '<li><strong>Hosting and infrastructure providers</strong>: Namecheap, which hosts the Website, our databases and our email, and Cloudflare, whose content delivery network delivers the Website and protects it against attacks (it handles every request, including your IP address);</li>',
        ],
    ],
];
