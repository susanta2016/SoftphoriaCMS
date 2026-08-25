<?php

namespace App\Http\Controllers\PoetryProse;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public Poetry/Prose list + detail — fully public once Published
 * (client-confirmed: no membership/entitlement gate on viewing in this
 * module). Mirrors PageController's thin shape: no auth, published-only,
 * 404 on anything else.
 */
class PoetryProseController extends Controller
{
    public function index(Request $request, SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $entries = PoetryProse::query()
            ->published()
            ->with(['featuredImage', 'author', 'categories', 'tags'])
            ->when($request->filled('content_type'), fn ($query) => $query->where('content_type', $request->string('content_type')))
            ->when($request->filled('category'), fn ($query) => $query->whereHas('categories', fn ($q) => $q->where('categories.slug', $request->string('category'))))
            ->when($request->filled('collection'), fn ($query) => $query->whereHas('collection', fn ($q) => $q->where('slug', $request->string('collection'))))
            ->orderByDesc('publish_at')
            ->paginate(12)
            ->withQueryString();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Poetry/Prose — {$chrome['siteName']}",
            'description' => 'Essays, reflections, hymns, poetry, and articles.',
            'canonical' => route('poetry-prose.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('poetry-prose.index', [
            ...$chrome,
            'seo' => $seo,
            'entries' => $entries,
            'contentTypes' => PoetryProseContentType::options(),
            'collections' => PoetryProseCollection::query()->where('status', PoetryProseStatus::Published)->orderBy('title')->get(),
        ]);
    }

    public function show(PoetryProse $poetryProse, SettingsRepository $settings): View
    {
        abort_unless($poetryProse->status === PoetryProseStatus::Published, 404);

        $chrome = $this->siteChrome($settings);
        $poetryProse->load(['featuredImage', 'author', 'categories', 'tags', 'collection']);

        $seo = SeoTagBuilder::build($poetryProse->seo, [
            'title' => "{$poetryProse->title} — {$chrome['siteName']}",
            'description' => str($poetryProse->body)->stripTags()->limit(160)->toString(),
            'canonical' => route('poetry-prose.show', $poetryProse),
            'type' => 'article',
            'image' => $poetryProse->featuredImage,
            'published_at' => $poetryProse->publish_at,
            'modified_at' => $poetryProse->updated_at,
            'author_name' => $poetryProse->author?->name,
        ], $chrome['general']);

        return view('poetry-prose.show', [
            ...$chrome,
            'seo' => $seo,
            'entry' => $poetryProse,
        ]);
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
