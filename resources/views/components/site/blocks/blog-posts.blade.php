{{--
    The Latest Blog Posts section type (the homepage's "Latest Insights"):
    the newest live posts, up to content_json.limit (default 3), rendered
    with the blog's own card and Blog Settings' card style. Hidden while the
    Blog Posts feature is off or nothing is published.
--}}
@props(['section', 'content'])

@php
    $features = app(\App\Shared\Support\Features\Features::class);
    $posts = $features->enabled('blog.posts')
        ? \App\Models\BlogPost::query()
            ->live()
            ->with(['cover', 'category', 'author'])
            ->latestFirst()
            ->limit(max(1, min(12, (int) ($content['limit'] ?? 3))))
            ->get()
        : collect();
    $blog = app(\App\Shared\Support\Blog\BlogSettingsRepository::class);
@endphp

@if ($posts->isNotEmpty())
    <x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
        <x-site.section-heading
            :eyebrow="$content['eyebrow'] ?? null"
            :heading="$content['heading'] ?? null"
            :description="$content['description'] ?? null"
            :link-label="$content['link_label'] ?? null"
            :link-url="($content['link_url'] ?? null) ?: route('blog.index')"
        />

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <x-blog.card
                    :post="$post"
                    :style="$blog->get('card_style')"
                    :show-author="$blog->get('show_author')"
                    :show-reading-time="$blog->get('show_reading_time')"
                    :show-category="$features->enabled('blog.categories')"
                />
            @endforeach
        </div>
    </x-site.band>
@endif
