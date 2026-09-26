<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolRedirect;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Blog\BlogContent;
use App\Shared\Support\Features\Features;
use App\Shared\Support\Seo\SchemaOrg;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Tools\ToolSettings;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The public Tools pages: the /tools hub and /tools/{slug} (both behind
 * feature:tools), plus the admin-only preview, which renders the exact same
 * page from the same method — current content, SEO, the real deployed
 * functionality, related tools and CTA — marked noindex and never listed.
 *
 * Only live tools (Published + functionality present) are ever public:
 * anywhere else a tool would appear — hub, related tools, sitemap — uses
 * Tool::live() too. An old slug of a once-public tool 301s to its new URL.
 */
class ToolController extends Controller
{
    use ResolvesSiteChrome;

    public function __construct(
        private readonly ToolSettings $settings,
        private readonly SettingsRepository $siteSettings,
        private readonly Features $features,
    ) {}

    public function index(): View
    {
        $tools = Tool::query()->live()->ordered()->with('category')->get();
        $chrome = $this->siteChrome($this->siteSettings);
        $categories = ToolCategory::query()->ordered()->whereIn('id', $tools->pluck('tool_category_id')->filter()->unique())->get();

        return view('tools.index', $chrome + [
            'tools' => $tools,
            'featured' => $tools->where('is_featured', true)->values(),
            'categories' => $categories,
            'settings' => $this->settings,
            'seo' => SeoTagBuilder::build(null, [
                'title' => $this->settings->get('meta_title') ?: "Free Tools — {$chrome['siteName']}",
                'description' => $this->settings->get('meta_description') ?: $this->settings->get('intro'),
                'canonical' => route('tools.index'),
                'force_canonical' => true,
                'type' => 'website',
                'structured_data' => SchemaOrg::collectionPage(
                    $this->settings->get('title'),
                    route('tools.index'),
                    [['Tools', route('tools.index')]],
                    $tools->map(fn (Tool $tool): array => [$tool->name, $tool->url()])->all(),
                ),
            ], $chrome['general']),
        ]);
    }

    public function show(string $tool): View|RedirectResponse
    {
        $record = Tool::query()->live()->where('slug', $tool)->first();

        if ($record === null) {
            $redirect = ToolRedirect::query()->with('tool')->where('old_slug', $tool)->first();

            abort_unless($redirect?->tool?->isLive(), 404);

            return redirect()->to($redirect->tool->url(), 301);
        }

        return $this->render($record, false);
    }

    public function preview(Tool $tool): Response
    {
        abort_unless(Auth::check() && Auth::user()->canAccessPanel(Filament::getPanel('admin')), 403);

        return response($this->render($tool, true))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function render(Tool $tool, bool $isPreview): View
    {
        $tool->load(['category', 'seo.ogImage', 'service']);
        $chrome = $this->siteChrome($this->siteSettings);
        $functionality = $tool->functionalityModule();
        $faqs = $tool->faqs()->where('is_visible', true)->get();
        $url = $tool->url();

        $breadcrumbs = [['Tools', route('tools.index')], [$tool->name, $url]];

        $seo = SeoTagBuilder::build($tool->seo, [
            'title' => "{$tool->name} — {$chrome['siteName']}",
            'description' => $tool->short_description ?: $tool->introduction,
            'canonical' => $url,
            'type' => 'website',
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@graph' => array_values(array_filter([
                    $functionality ? array_filter([
                        '@type' => 'WebApplication',
                        'name' => $tool->name,
                        'url' => $tool->seo?->canonical_url ?: $url,
                        'description' => $tool->seo?->meta_description ?: $tool->short_description,
                        'applicationCategory' => $functionality->applicationCategory(),
                        'operatingSystem' => 'Any',
                        'browserRequirements' => 'Requires JavaScript',
                        'isAccessibleForFree' => true,
                        'softwareVersion' => $functionality->version(),
                        'provider' => ['@type' => 'Organization', 'name' => $chrome['siteName'], 'url' => route('home')],
                    ]) : null,
                    SchemaOrg::faqPage($faqs->map(fn ($faq): array => ['question' => $faq->question, 'answer' => $faq->answer])->all()),
                    SchemaOrg::breadcrumbs($breadcrumbs),
                ])),
            ],
        ], $chrome['general']);

        // A preview is never indexable, whatever the tool's own setting says.
        if ($isPreview) {
            $seo['robots'] = SeoTagBuilder::ROBOTS_NOINDEX;
        }

        return view('tools.show', $chrome + [
            'tool' => $tool,
            'functionality' => $functionality,
            'isPreview' => $isPreview,
            'sections' => collect([
                'how-it-works' => ['How it works', $tool->how_it_works],
                'use-cases' => ['Use cases', $tool->use_cases],
                'more' => [null, $tool->additional_content],
            ])->filter(fn (array $section): bool => filled(strip_tags((string) $section[1], '<img>')))
                ->map(fn (array $section): array => [$section[0], BlogContent::prepare($section[1])['html']]),
            'importantNotes' => filled(strip_tags((string) $tool->important_notes)) ? $tool->important_notes : null,
            'faqs' => $faqs,
            'related' => $this->relatedTools($tool),
            'service' => $this->service($tool),
            'cta' => $this->cta($tool),
            'seo' => $seo,
        ]);
    }

    /**
     * The tools chosen in the admin, or else other tools in the same
     * category — live ones only.
     *
     * @return Collection<int, Tool>
     */
    private function relatedTools(Tool $tool): Collection
    {
        $chosen = $tool->relatedTools()->live()->with('category')->get();

        if ($chosen->isNotEmpty() || $tool->tool_category_id === null) {
            return $chosen;
        }

        return Tool::query()->live()->ordered()->with('category')
            ->where('tool_category_id', $tool->tool_category_id)
            ->whereKeyNot($tool->getKey())
            ->limit(3)
            ->get();
    }

    private function service(Tool $tool): ?Service
    {
        return $tool->service?->is_published && $this->features->enabled('services') ? $tool->service : null;
    }

    /**
     * The tool's own call to action, falling back to Tools Settings.
     *
     * @return array{heading: ?string, text: ?string, label: ?string, url: ?string}
     */
    private function cta(Tool $tool): array
    {
        $own = filled($tool->cta_heading);

        return [
            'heading' => $own ? $tool->cta_heading : $this->settings->get('cta_heading'),
            'text' => $own ? $tool->cta_text : $this->settings->get('cta_text'),
            'label' => ($own ? $tool->cta_label : null) ?: $this->settings->get('cta_label'),
            'url' => ($own ? $tool->cta_url : null) ?: $this->settings->get('cta_url'),
        ];
    }
}
