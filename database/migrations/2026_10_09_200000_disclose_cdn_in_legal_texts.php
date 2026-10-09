<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cloudflare in front of the site: published Cookie / Privacy Policy pages
 * get the wording from database/seeders/data/legal-cdn-wording.php (cookie-
 * based tools need consent, cookieless measurement is listed separately,
 * Cloudflare is named as an infrastructure provider and its security
 * cookies are listed) — only where the old text is still word for word, so
 * admin edits are left alone.
 *
 * Plain query-builder code on purpose, like 2026_09_26_210000.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap(false);
    }

    public function down(): void
    {
        $this->swap(true);
    }

    private function swap(bool $reverse): void
    {
        foreach (require database_path('seeders/data/legal-cdn-wording.php') as $slug => $pairs) {
            $pageId = DB::table('pages')->where('slug', $slug)->value('id');

            if (! $pageId) {
                continue;
            }

            foreach (DB::table('page_sections')->where('page_id', $pageId)->get() as $section) {
                $content = json_decode($section->content_json ?? '[]', true) ?: [];
                $body = $content['body'] ?? null;

                if (! is_string($body)) {
                    continue;
                }

                foreach ($pairs as [$old, $new]) {
                    [$from, $to] = $reverse ? [$new, $old] : [$old, $new];

                    // Forward: skip pages that already have the new text (one
                    // "new" text contains its "old" one, the added cookie row).
                    if (str_contains($body, $from) && ($reverse || ! str_contains($body, $to))) {
                        $body = str_replace($from, $to, $body);
                    }
                }

                if ($body !== $content['body']) {
                    $content['body'] = $body;
                    DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
                }
            }
        }
    }
};
