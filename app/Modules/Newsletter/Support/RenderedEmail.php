<?php

namespace App\Modules\Newsletter\Support;

/**
 * A rendered campaign email. As a snapshot (NewsletterRenderer::snapshot())
 * the html/text still hold per-recipient markers; after personalize() they
 * are final. $links maps each tracked link's hash to its real URL — the
 * click redirect only ever sends people to a URL in this map.
 */
final class RenderedEmail
{
    /**
     * @param  array<string, string>  $links
     */
    public function __construct(
        public readonly string $html,
        public readonly string $text,
        public readonly array $links = [],
    ) {}
}
