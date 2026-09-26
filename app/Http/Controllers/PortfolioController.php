<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\PortfolioItem;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SchemaOrg;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /portfolio — every published portfolio project (Admin → Portfolio), with
 * category chips that filter via ?category= (swapped in place through the
 * shared [data-async-region] script in resources/js/app.js, crawlable as
 * plain links without JS). The homepage Featured Portfolio section's
 * "View All Projects" link lands here. Behind feature:portfolio.
 */
class PortfolioController extends Controller
{
    public function __invoke(Request $request, SettingsRepository $settings): Response
    {
        $items = PortfolioItem::query()->published()->ordered()->with('cover')->get();
        $categories = $items->pluck('category')->filter()->unique()->values();

        $active = (string) $request->query('category', '');
        $active = $categories->first(fn (string $category): bool => $category === $active) ?? '';
        $shown = $active === '' ? $items : $items->where('category', $active)->values();

        $siteName = $settings->get('general', 'site_name') ?: config('app.name');
        $logoId = $settings->get('general', 'logo_media_id');

        $data = [
            'items' => $shown,
            'categories' => $categories,
            'active' => $active,
            'siteName' => $siteName,
            'tagline' => $settings->get('general', 'tagline'),
            'logo' => $logoId ? Media::find($logoId) : null,
            'seo' => SeoTagBuilder::build(null, [
                'title' => ($active !== '' ? "{$active} projects" : 'Portfolio')." — {$siteName}",
                'description' => "Selected projects delivered by {$siteName} — websites, custom software, e-commerce, cloud and integrations.",
                // Filtered views are the same projects again: canonicalise to the full list.
                'canonical' => route('portfolio.index'),
                'type' => 'website',
                'structured_data' => SchemaOrg::collectionPage(
                    'Portfolio',
                    route('portfolio.index'),
                    [['Portfolio', route('portfolio.index')]],
                    $shown->map(fn (PortfolioItem $item): array => [$item->title, $item->link_url ?: route('portfolio.index')])->all(),
                ),
            ]),
        ];

        return response()
            ->view($request->ajax() ? 'portfolio.partials.page' : 'portfolio.index', $data)
            ->header('Vary', 'X-Requested-With');
    }
}
