<?php

namespace App\Tools\SeoChecker;

/**
 * The deterministic score shown with a report (also explained on the page):
 *
 *   score = round(100 × Σ(weight × credit) ÷ Σ weight), over checked findings
 *   credit: passed 1, warning 0.5, critical 0; "not checked" is left out
 *
 * Any critical finding caps the score at BLOCKER_CAP and the readiness
 * label says "Not ready to launch", so a high score can never hide a
 * blocker. There is no score at all when the page itself couldn't be
 * analysed (fetch failed, non-2xx, not HTML) — that is not a zero.
 *
 * This is a checklist score for these checks only — not a Google score and
 * not a ranking prediction.
 */
final class Scorer
{
    public const BLOCKER_CAP = 59;

    public const CREDIT = [Finding::PASSED => 1.0, Finding::WARNING => 0.5, Finding::CRITICAL => 0.0];

    /** Check weights: launch-critical checks weigh most. Unlisted checks weigh 1. */
    public const WEIGHTS = [
        'http.status' => 5, 'http.redirects' => 2, 'http.https' => 4, 'http.https_redirect' => 2, 'http.staging_host' => 2,
        'meta.title' => 5, 'meta.description' => 3, 'meta.canonical' => 4, 'meta.canonical_target' => 3,
        'meta.lang' => 2, 'meta.charset' => 1, 'meta.viewport' => 3, 'meta.favicon' => 1,
        'index.meta_robots' => 6, 'index.x_robots_tag' => 6, 'index.robots_txt' => 6, 'index.sitemap' => 3,
        'index.sitemap_urls' => 2, 'index.staging_references' => 3,
        'social.og_title' => 2, 'social.og_description' => 1, 'social.og_url' => 1, 'social.og_image' => 2, 'social.twitter_card' => 1,
        'structure.h1' => 3, 'structure.heading_order' => 1, 'structure.image_alt' => 2, 'structure.links' => 1,
        'structure.link_sample' => 2, 'structure.structured_data' => 1, 'structure.content' => 2,
        'perf.response_time' => 2, 'perf.html_size' => 1, 'perf.compression' => 1, 'perf.page_speed' => 0,
    ];

    public static function weight(string $id): int
    {
        return self::WEIGHTS[$id] ?? 1;
    }

    /**
     * @param  list<Finding>  $findings
     */
    public function score(array $findings): ?int
    {
        $total = 0.0;
        $earned = 0.0;
        $critical = false;

        foreach ($findings as $finding) {
            if (! isset(self::CREDIT[$finding->severity])) {
                continue;
            }
            $weight = self::weight($finding->id);
            $total += $weight;
            $earned += $weight * self::CREDIT[$finding->severity];
            $critical = $critical || $finding->severity === Finding::CRITICAL;
        }

        if ($total <= 0) {
            return null;
        }

        $score = (int) round(100 * $earned / $total);

        return $critical ? min($score, self::BLOCKER_CAP) : $score;
    }

    /**
     * @param  array<string, int>  $counts  per severity
     * @return array{level: string, label: string, summary: string}
     */
    public function readiness(array $counts, bool $analysed): array
    {
        $critical = $counts[Finding::CRITICAL] ?? 0;
        $warnings = $counts[Finding::WARNING] ?? 0;

        return match (true) {
            $critical > 0 => [
                'level' => 'blocked',
                'label' => 'Not ready to launch',
                'summary' => $critical === 1
                    ? '1 launch blocker found. Fix it first — a good score elsewhere doesn\'t make up for it.'
                    : "{$critical} launch blockers found. Fix them first — a good score elsewhere doesn't make up for them.",
            ],
            ! $analysed => [
                'level' => 'incomplete',
                'label' => 'Page checks skipped',
                'summary' => 'The page didn\'t return a normal HTML response, so its content couldn\'t be checked.',
            ],
            $warnings > 0 => [
                'level' => 'review',
                'label' => 'Review before launch',
                'summary' => $warnings === 1 ? 'No launch blockers found, but 1 warning is worth reviewing.' : "No launch blockers found, but {$warnings} warnings are worth reviewing.",
            ],
            default => [
                'level' => 'ready',
                'label' => 'No problems found',
                'summary' => 'No blockers or warnings in the checks we ran. That isn\'t a guarantee of indexing or rankings.',
            ],
        };
    }
}
