<?php

namespace App\Http\Controllers\Blog;

use App\Enums\BlogReaction;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Blog\BlogContent;
use App\Shared\Support\Blog\BlogSettingsRepository;
use App\Shared\Support\Features\Features;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The public blog: the /blog landing page, category and tag archives, a
 * single post, and the RSS feed. All routes sit behind feature:blog.posts
 * (plus feature:blog.categories / feature:blog.tags for the archives).
 *
 * Listing pages follow the site's async pagination/filter rule: normal
 * links and a GET search form that work without JavaScript (and stay
 * crawlable), which resources/js/app.js ([data-async-region]) upgrades to
 * fetch + region swap. A fetch request gets only the results partial.
 *
 * SEO: self-referencing canonicals (including ?page=N), BlogPosting +
 * BreadcrumbList JSON-LD on posts, CollectionPage JSON-LD on listings,
 * RSS autodiscovery, and noindex,follow on tag archives and searches.
 */
class BlogController extends Controller
{
    public function __construct(
        private readonly BlogSettingsRepository $blog,
        private readonly Features $features,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $featured = null;

        if ($search === '' && $request->integer('page', 1) <= 1 && $this->blog->get('show_featured')) {
            $featured = $this->liveQuery()->where('is_featured', true)->latestFirst()->first();
        }

        $posts = $this->liveQuery()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$this->escapeLike($search).'%')
                ->orWhere('excerpt', 'like', '%'.$this->escapeLike($search).'%')))
            ->when($featured, fn (Builder $query) => $query->whereKeyNot($featured->getKey()))
            ->latestFirst()
            ->paginate($this->perPage())
            ->withQueryString();

        $title = $this->blog->get('title');
        $siteName = $this->siteName();

        return $this->listing($request, [
            'posts' => $posts,
            'featured' => $featured,
            'search' => $search,
            'heading' => $title,
            'intro' => $this->blog->get('intro'),
            'eyebrow' => 'Blog',
            'activeCategory' => null,
            'activeTag' => null,
            'breadcrumbs' => [['label' => 'Blog', 'url' => route('blog.index')]],
            'seo' => SeoTagBuilder::build(null, [
                'title' => $this->pageTitle($this->blog->get('meta_title') ?: "{$title} — {$siteName}", $posts->currentPage()),
                'description' => $this->blog->get('meta_description') ?: $this->blog->get('intro'),
                'canonical' => $this->canonical(route('blog.index'), $posts->currentPage()),
                'type' => 'website',
                // Search result pages are thin/duplicate content.
                'robots' => $search !== '' ? 'noindex, follow' : null,
                'structured_data' => $this->collectionSchema($title, route('blog.index'), [['Blog', route('blog.index')]]),
            ]),
        ]);
    }

    public function category(Request $request, BlogCategory $category): Response
    {
        $posts = $this->liveQuery()
            ->where('blog_category_id', $category->getKey())
            ->latestFirst()
            ->paginate($this->perPage())
            ->withQueryString();

        $category->loadMissing('seo');

        return $this->listing($request, [
            'posts' => $posts,
            'featured' => null,
            'search' => '',
            'heading' => $category->name,
            'intro' => $category->description,
            'eyebrow' => 'Category',
            'activeCategory' => $category,
            'activeTag' => null,
            'breadcrumbs' => [
                ['label' => 'Blog', 'url' => route('blog.index')],
                ['label' => $category->name, 'url' => $category->url()],
            ],
            'seo' => SeoTagBuilder::build($category->seo, [
                'title' => $this->pageTitle("{$category->name} — {$this->blog->get('title')} — {$this->siteName()}", $posts->currentPage()),
                'description' => $category->description ?: "Articles about {$category->name} from {$this->siteName()}.",
                'canonical' => $this->canonical($category->url(), $posts->currentPage()),
                'force_canonical' => $posts->currentPage() > 1,
                'type' => 'website',
                'structured_data' => $this->collectionSchema($category->name, $category->url(), [
                    ['Blog', route('blog.index')],
                    [$category->name, $category->url()],
                ]),
            ]),
        ]);
    }

    public function tag(Request $request, BlogTag $tag): Response
    {
        $posts = $this->liveQuery()
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->getKey()))
            ->latestFirst()
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listing($request, [
            'posts' => $posts,
            'featured' => null,
            'search' => '',
            'heading' => '#'.$tag->name,
            'intro' => "Every article tagged “{$tag->name}”.",
            'eyebrow' => 'Tag',
            'activeCategory' => null,
            'activeTag' => $tag,
            'breadcrumbs' => [
                ['label' => 'Blog', 'url' => route('blog.index')],
                ['label' => '#'.$tag->name, 'url' => $tag->url()],
            ],
            'seo' => SeoTagBuilder::build(null, [
                'title' => $this->pageTitle("{$tag->name} — {$this->blog->get('title')} — {$this->siteName()}", $posts->currentPage()),
                'description' => "Articles tagged {$tag->name} on the {$this->siteName()} blog.",
                'canonical' => $this->canonical($tag->url(), $posts->currentPage()),
                'type' => 'website',
                'robots' => 'noindex, follow',
            ]),
        ]);
    }

    public function show(BlogPost $post): View
    {
        // Admins can preview a draft/scheduled post; everyone else gets 404.
        $isPreview = ! $post->isLive();
        abort_if($isPreview && ! $this->canPreview(), 404);

        $post->load(['cover', 'author', 'seo', 'tags'])
            ->loadMissing(['category']);

        $content = BlogContent::prepare($post->body);
        $user = Auth::user();

        $commentsOn = $this->features->enabled('blog.comments');
        $comments = $commentsOn
            ? $post->visibleComments()->with('user')->oldest()->get()
            : collect();

        $reactionsOn = $this->features->enabled('blog.reactions');
        $reactionCounts = $reactionsOn
            ? $post->reactions()->toBase()->selectRaw('reaction, count(*) as total')->groupBy('reaction')->pluck('total', 'reaction')->map(fn ($n): int => (int) $n)->all()
            : [];
        $myReactions = $reactionsOn && $user
            ? $post->reactions()->where('user_id', $user->getKey())->pluck('reaction')->map(fn (BlogReaction $r): string => $r->value)->all()
            : [];

        $related = $this->blog->get('show_related') ? $this->relatedPosts($post) : collect();

        $coverUrl = $this->mediaUrl($post->cover);
        $siteName = $this->siteName();
        $categoriesOn = $this->features->enabled('blog.categories');

        $breadcrumbs = [['label' => 'Blog', 'url' => route('blog.index')]];
        if ($categoriesOn && $post->category) {
            $breadcrumbs[] = ['label' => $post->category->name, 'url' => $post->category->url()];
        }
        $breadcrumbs[] = ['label' => $post->title, 'url' => $post->url()];

        $canonical = $post->seo?->canonical_url ?: $post->url();
        $description = $post->excerpt ?: Str::limit(trim(strip_tags((string) $post->body)), 160);

        return view('blog.show', $this->chrome() + [
            'post' => $post,
            'isPreview' => $isPreview,
            'bodyHtml' => $content['html'],
            'toc' => $this->blog->get('show_toc') ? $content['toc'] : [],
            'comments' => $comments,
            'commentsOn' => $commentsOn && $post->allow_comments,
            'reportsOn' => $this->features->enabled('blog.comment_reports'),
            'reactionsOn' => $reactionsOn,
            'reactionCounts' => $reactionCounts,
            'myReactions' => $myReactions,
            'related' => $related,
            'categoriesOn' => $categoriesOn,
            'tagsOn' => $this->features->enabled('blog.tags'),
            'newsletterOn' => $this->features->enabled('newsletter') && $this->blog->get('show_newsletter'),
            'blog' => $this->blog,
            'coverUrl' => $coverUrl,
            'breadcrumbs' => $breadcrumbs,
            'seo' => SeoTagBuilder::build($post->seo, [
                'title' => "{$post->title} — {$siteName}",
                'description' => $description,
                'canonical' => $post->url(),
                'image' => $post->cover,
                'type' => 'article',
                'published_at' => $post->published_at,
                'modified_at' => $post->updated_at,
                'author_name' => $post->author?->name,
                'robots' => $isPreview ? SeoTagBuilder::ROBOTS_NOINDEX : null,
                'structured_data' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        array_filter([
                            '@type' => 'BlogPosting',
                            'headline' => Str::limit($post->title, 110, ''),
                            'description' => $description,
                            'image' => $coverUrl,
                            'datePublished' => $post->published_at?->toAtomString(),
                            'dateModified' => $post->updated_at?->toAtomString(),
                            'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
                            'publisher' => array_filter([
                                '@type' => 'Organization',
                                'name' => $siteName,
                                'logo' => ($logo = $this->logoUrl()) ? ['@type' => 'ImageObject', 'url' => $logo] : null,
                            ]),
                            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
                            'articleSection' => $post->category?->name,
                            'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
                            'wordCount' => str_word_count(strip_tags((string) $post->body)),
                            'commentCount' => $commentsOn ? $comments->count() : null,
                        ], fn ($value): bool => $value !== null && $value !== ''),
                        $this->breadcrumbSchema(array_map(fn (array $crumb): array => [$crumb['label'], $crumb['url']], $breadcrumbs)),
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Sends a guest to log in (or register) and back to the post afterwards
     * — the target of the "Log in to comment/react" links. A GET route, so
     * it is safe as the session's "intended" URL.
     */
    public function join(BlogPost $post): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($post->url().'#discussion');
        }

        return redirect()->guest(route('login'));
    }

    public function feed(): Response
    {
        $posts = $this->liveQuery()->latestFirst()->limit(20)->get();

        return response()
            ->view('blog.feed', [
                'posts' => $posts,
                'title' => $this->blog->get('title').' — '.$this->siteName(),
                'description' => $this->blog->get('intro'),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function listing(Request $request, array $data): Response
    {
        $categoriesOn = $this->features->enabled('blog.categories');

        $data += $this->chrome() + [
            'categories' => $categoriesOn
                ? BlogCategory::query()->ordered()->withCount(['posts' => fn (Builder $query) => $query->live()])->get()->where('posts_count', '>', 0)
                : collect(),
            'categoriesOn' => $categoriesOn,
            'tagsOn' => $this->features->enabled('blog.tags'),
            'newsletterOn' => $this->features->enabled('newsletter') && $this->blog->get('show_newsletter'),
            'blog' => $this->blog,
        ];

        // Async region swap (resources/js/app.js): only the listing block.
        // Vary: the same URL answers with a fragment or a full page, so
        // caches (and the browser's back/forward cache) must keep them apart.
        return response()
            ->view($request->ajax() ? 'blog.partials.page' : 'blog.index', $data)
            ->header('Vary', 'X-Requested-With');
    }

    /**
     * @return Builder<BlogPost>
     */
    private function liveQuery(): Builder
    {
        return BlogPost::query()->live()->with(['cover', 'category', 'author']);
    }

    /**
     * @return Collection<int, BlogPost>
     */
    private function relatedPosts(BlogPost $post): Collection
    {
        $tagIds = $post->tags->modelKeys();

        return $this->liveQuery()
            ->whereKeyNot($post->getKey())
            ->where(fn (Builder $query) => $query
                ->when($post->blog_category_id, fn (Builder $q) => $q->where('blog_category_id', $post->blog_category_id))
                ->when($tagIds !== [], fn (Builder $q) => $q->orWhereHas('tags', fn (Builder $t) => $t->whereKey($tagIds))))
            ->latestFirst()
            ->limit(3)
            ->get()
            ->whenEmpty(fn () => $this->liveQuery()->whereKeyNot($post->getKey())->latestFirst()->limit(3)->get());
    }

    /**
     * The shared header/footer props every public page passes.
     *
     * @return array{siteName: string, tagline: ?string, logo: ?Media}
     */
    private function chrome(): array
    {
        $logoId = $this->settings->get('general', 'logo_media_id');

        return [
            'siteName' => $this->siteName(),
            'tagline' => $this->settings->get('general', 'tagline'),
            'logo' => $logoId ? Media::find($logoId) : null,
        ];
    }

    private function canPreview(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->canAccessPanel(filament()->getPanel('admin'));
    }

    private function perPage(): int
    {
        return max(3, min(30, (int) $this->blog->get('per_page')));
    }

    private function siteName(): string
    {
        return $this->settings->get('general', 'site_name') ?: config('app.name');
    }

    private function logoUrl(): ?string
    {
        $id = $this->settings->get('general', 'logo_media_id');

        return $id ? $this->mediaUrl(Media::find($id)) : null;
    }

    private function mediaUrl(?Media $media): ?string
    {
        return $media ? Storage::disk($media->disk)->url($media->path) : null;
    }

    private function pageTitle(string $title, int $page): string
    {
        return $page > 1 ? "{$title} — Page {$page}" : $title;
    }

    private function canonical(string $url, int $page): string
    {
        return $page > 1 ? "{$url}?page={$page}" : $url;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed>
     */
    private function collectionSchema(string $name, string $url, array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'CollectionPage', 'name' => $name, 'url' => $url],
                $this->breadcrumbSchema($crumbs),
            ],
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed>
     */
    private function breadcrumbSchema(array $crumbs): array
    {
        $items = [['Home', route('home')], ...$crumbs];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ], $items, array_keys($items)),
        ];
    }
}
