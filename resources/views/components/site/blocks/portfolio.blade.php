{{--
    The Featured Portfolio section type: published portfolio items marked
    "Featured on homepage" in Admin → Portfolio, in sort order, up to
    content_json.limit (default 6). The section itself only carries the
    heading/links. Nothing renders while no item is featured. With no cover
    image, a branded placeholder (the item's icon on a navy-to-blue
    gradient) keeps the card shape.
--}}
@props(['section', 'content'])

@php
    // Hidden entirely while Features Activation has Portfolio off.
    $items = app(\App\Shared\Support\Features\Features::class)->enabled('portfolio')
        ? \App\Models\PortfolioItem::query()
            ->with('cover')
            ->published()
            ->featured()
            ->ordered()
            ->limit(max(1, min(12, (int) ($content['limit'] ?? 6))))
            ->get()
        : collect();
@endphp

@if ($items->isNotEmpty())
    <x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
        <x-site.section-heading
            :eyebrow="$content['eyebrow'] ?? null"
            :heading="$content['heading'] ?? null"
            :description="$content['description'] ?? null"
            :link-label="$content['link_label'] ?? null"
            :link-url="(($content['link_url'] ?? null) && $content['link_url'] !== '#') ? $content['link_url'] : route('portfolio.index')"
        />

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                <x-portfolio.card :item="$item" :link-label="$content['item_link_label'] ?? null"/>
            @endforeach
        </div>
    </x-site.band>
@endif
