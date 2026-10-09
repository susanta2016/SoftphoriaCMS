<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026_10_09_200000 and 2026_10_09_210000 replace policy text only where it
 * is word for word as seeded. A policy page saved once through the admin's
 * rich-text editor stores the same text in the editor's own HTML — list
 * items wrapped in <p>, table cells with rowspan/colspan="1" — so on the
 * live site the list items and the cookie row were skipped as if edited.
 *
 * This applies the same changes to that editor format (and to the seeded
 * format, for any page the earlier migrations missed), writing the new text
 * in whichever format the page uses. Text that differs in any other way is
 * still treated as the owner's edit and left alone.
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

    /**
     * slug => [[old, new], ...] in the seeded format.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function pairs(): array
    {
        $cdn = require database_path('seeders/data/legal-cdn-wording.php');
        [[$hostingCdn, $hostingFinal]] = (require database_path('seeders/data/legal-hosting-wording.php'))['privacy-policy'];

        return [
            'cookie-policy' => [$cdn['cookie-policy'][1]],
            'privacy-policy' => [
                [$cdn['privacy-policy'][1][0], $hostingFinal], // original AWS line → final hosting line
                [$hostingCdn, $hostingFinal, 'partial' => true], // in case only the first step ran
                $cdn['privacy-policy'][2],
            ],
        ];
    }

    private function swap(bool $reverse): void
    {
        foreach ($this->pairs() as $slug => $pairs) {
            $pageId = DB::table('pages')->where('slug', $slug)->value('id');

            if (! $pageId) {
                continue;
            }

            if ($reverse) {
                // Back to the original text; the "only the first step ran"
                // pair has no separate original to return to.
                $pairs = array_map(fn (array $pair): array => [$pair[1], $pair[0]], array_filter($pairs, fn (array $pair): bool => ! ($pair['partial'] ?? false)));
            }

            foreach (DB::table('page_sections')->where('page_id', $pageId)->get() as $section) {
                $content = json_decode($section->content_json ?? '[]', true) ?: [];
                $body = $content['body'] ?? null;

                if (! is_string($body)) {
                    continue;
                }

                foreach ($pairs as [$old, $new]) {
                    foreach ([fn (string $html): string => $html, $this->editorFormat(...)] as $format) {
                        [$from, $to] = [$format($old), $format($new)];

                        if (str_contains($body, $from) && ($reverse || ! str_contains($body, $to))) {
                            $body = str_replace($from, $to, $body);
                        }
                    }
                }

                if ($body !== $content['body']) {
                    $content['body'] = $body;
                    DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
                }
            }
        }
    }

    /** The same HTML as the rich-text editor saves it. */
    private function editorFormat(string $html): string
    {
        return str_replace(
            ["\n        ", '<li>', '</li>', '<td>'],
            ['', '<li><p>', '</p></li>', '<td rowspan="1" colspan="1">'],
            $html,
        );
    }
};
