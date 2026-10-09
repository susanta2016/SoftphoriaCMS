<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The published Privacy Policy named Amazon Web Services as the host;
 * softphoria.com is hosted by Namecheap. Swaps in the wording from
 * database/seeders/data/legal-hosting-wording.php, only where the text is
 * still word for word, so admin edits are left alone. Same approach as
 * 2026_10_09_200000.
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
        foreach (require database_path('seeders/data/legal-hosting-wording.php') as $slug => $pairs) {
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
                    $body = str_replace($from, $to, $body);
                }

                if ($body !== $content['body']) {
                    $content['body'] = $body;
                    DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
                }
            }
        }
    }
};
