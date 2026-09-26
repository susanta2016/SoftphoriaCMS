<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The footer's "Cookie Settings" link was replaced by the bottom-left
 * Consent Preferences button. An already-published Cookie Policy page
 * (LegalPagesSeeder never overwrites existing pages) still tells visitors
 * to use the old link — this swaps exactly that sentence, and only where
 * it is still word-for-word what the seeder wrote.
 */
return new class extends Migration
{
    private const OLD = '<li><strong>Cookie Settings:</strong> you can change your choices at any time using the <strong>Cookie Settings</strong> link at the bottom of every page.</li>';

    private const NEW = '<li><strong>Consent Preferences:</strong> you can change your choices at any time using the round cookie icon in the bottom-left corner of every page.</li>';

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(string $from, string $to): void
    {
        $pageId = DB::table('pages')->where('slug', 'cookie-policy')->value('id');

        if (! $pageId) {
            return;
        }

        foreach (DB::table('page_sections')->where('page_id', $pageId)->get() as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            $body = $content['body'] ?? null;

            if (is_string($body) && str_contains($body, $from)) {
                $content['body'] = str_replace($from, $to, $body);
                DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
            }
        }
    }
};
