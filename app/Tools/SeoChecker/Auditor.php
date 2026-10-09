<?php

namespace App\Tools\SeoChecker;

use App\Tools\SeoChecker\Checks\CheckGroup;
use App\Tools\SeoChecker\Checks\HttpChecks;
use App\Tools\SeoChecker\Checks\IndexabilityChecks;
use App\Tools\SeoChecker\Checks\MetadataChecks;
use App\Tools\SeoChecker\Checks\PerformanceChecks;
use App\Tools\SeoChecker\Checks\SocialChecks;
use App\Tools\SeoChecker\Checks\StructureChecks;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one audit of one page and returns the report as plain data:
 * fetch (SafeFetcher) → parse (PageDocument) → checks (Checks\*) →
 * score (Scorer). Presentation is entirely in the browser.
 *
 * Nothing is stored: the report is built, returned and forgotten.
 */
class Auditor
{
    /** Seconds after which no new follow-up request is started. */
    public const TIME_BUDGET = 25;

    public const PAGE_MAX_BYTES = 3_000_000;

    /** @var list<class-string<CheckGroup>> */
    public const GROUPS = [HttpChecks::class, MetadataChecks::class, IndexabilityChecks::class, SocialChecks::class, StructureChecks::class, PerformanceChecks::class];

    public function __construct(
        private readonly SafeFetcher $fetcher,
        private readonly Scorer $scorer,
    ) {}

    /**
     * @param  string  $url  already normalized by UrlGuard
     * @param  string  $intent  'live' | 'staging'
     * @return array<string, mixed>
     */
    public function audit(string $url, string $intent): array
    {
        $deadline = microtime(true) + self::TIME_BUDGET;

        try {
            $page = $this->fetcher->fetch($url, ['max_bytes' => self::PAGE_MAX_BYTES, 'timeout' => 12, 'connect_timeout' => 5, 'max_redirects' => 5]);
        } catch (AuditException $e) {
            return $this->unavailable($url, $intent, $e->reason, $e->getMessage());
        }

        if ($page->truncated) {
            return $this->unavailable($url, $intent, 'too_large', 'The page is larger than '.(self::PAGE_MAX_BYTES / 1_000_000).' MB, which is more than this checker reads.');
        }

        $analysable = $page->successful();

        if ($analysable && ! $page->isHtml()) {
            return $this->unavailable($url, $intent, 'not_html', 'The address returned '.($page->contentType() ?? 'a non-HTML response').', not an HTML page, so there is nothing to audit.');
        }

        $doc = $analysable ? new PageDocument($page->body) : null;
        $context = new AuditContext($url, $intent, $page, $doc, $this->fetcher, $deadline);
        $findings = [];

        foreach ($analysable ? self::GROUPS : [HttpChecks::class] as $class) {
            $group = new $class;

            try {
                array_push($findings, ...$group->run($context));
            } catch (Throwable $e) {
                Log::warning('SEO checker: a check group failed', ['group' => class_basename($class), 'exception' => $e::class, 'message' => $e->getMessage()]);
                $findings[] = new Finding($class::CATEGORY.'.error', $class::CATEGORY, Finding::NOT_CHECKED, $class::LABEL.' checks could not be completed',
                    ['An unexpected problem stopped these checks.'], 'These checks are part of the pre-launch review.', 'Run the audit again. If it keeps failing, contact us.');
            }
        }

        return $this->report($url, $intent, $page, $doc, $context, $findings, $analysable);
    }

    /**
     * @param  list<Finding>  $findings
     * @return array<string, mixed>
     */
    private function report(string $url, string $intent, FetchResult $page, ?PageDocument $doc, AuditContext $context, array $findings, bool $analysable): array
    {
        $counts = $this->counts($findings);
        $categories = [];

        foreach (self::GROUPS as $class) {
            $own = array_values(array_filter($findings, fn (Finding $f): bool => $f->category === $class::CATEGORY));
            $categories[] = ['key' => $class::CATEGORY, 'label' => $class::LABEL, 'counts' => $this->counts($own), 'checked' => $own !== []];
        }

        // Most urgent first within each severity: heaviest checks first.
        $order = [Finding::CRITICAL => 0, Finding::WARNING => 1, Finding::PASSED => 2, Finding::NOT_CHECKED => 3];
        $sorted = $findings;
        usort($sorted, fn (Finding $a, Finding $b): int => [$order[$a->severity], -Scorer::weight($a->id)] <=> [$order[$b->severity], -Scorer::weight($b->id)]);

        return [
            'status' => 'complete',
            'audited_at' => now()->toIso8601String(),
            'intent' => $intent,
            'submitted_url' => $url,
            'final_url' => $page->finalUrl,
            'http' => [
                'status' => $page->status,
                'response_ms' => $page->responseMs,
                'total_ms' => $page->totalMs,
                'content_type' => $page->contentType(),
                'bytes' => strlen($page->body),
                'redirects' => $page->redirects,
            ],
            'analysed' => $analysable,
            'score' => $analysable ? $this->scorer->score($findings) : null,
            'readiness' => $this->scorer->readiness($counts, $analysable),
            'counts' => $counts,
            'categories' => $categories,
            'findings' => array_map(fn (Finding $f): array => $f->toArray(), $sorted),
            'preview' => $this->preview($context->shared['preview'] ?? null),
            'platform' => $doc ? $this->platform($page->body) : null,
            'requests' => $context->requestCount() + 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $url, string $intent, string $code, string $message): array
    {
        return [
            'status' => 'unavailable',
            'audited_at' => now()->toIso8601String(),
            'intent' => $intent,
            'submitted_url' => $url,
            'score' => null,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }

    /**
     * @param  list<Finding>  $findings
     * @return array<string, int>
     */
    private function counts(array $findings): array
    {
        $counts = [Finding::CRITICAL => 0, Finding::WARNING => 0, Finding::PASSED => 0, Finding::NOT_CHECKED => 0];
        foreach ($findings as $finding) {
            $counts[$finding->severity]++;
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>|null
     */
    private function preview(?array $preview): ?array
    {
        if ($preview === null) {
            return null;
        }

        foreach (['title' => 200, 'description' => 300, 'site_name' => 100, 'domain' => 253, 'card' => 40] as $key => $limit) {
            $preview[$key] = $preview[$key] === null ? null : PageDocument::clean((string) $preview[$key], $limit);
        }

        return $preview;
    }

    /** Only used to choose which Softphoria service the report suggests. */
    private function platform(string $html): ?string
    {
        $head = substr($html, 0, 400_000);

        return match (true) {
            stripos($head, 'elementor') !== false => 'elementor',
            stripos($head, '/wp-content/') !== false || stripos($head, 'content="WordPress') !== false => 'wordpress',
            default => null,
        };
    }
}
