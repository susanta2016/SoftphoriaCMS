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

        {{-- Column count follows the item count, so one or two featured projects fill the row as larger cards instead of leaving empty grid cells. --}}
        <div @class([
            'mt-10 grid gap-6 lg:gap-8',
            'mx-auto max-w-3xl' => $items->count() === 1,
            'md:grid-cols-2' => $items->count() === 2 || $items->count() === 4,
            'sm:grid-cols-2 lg:grid-cols-3' => $items->count() === 3 || $items->count() > 4,
        ])>
            @foreach ($items as $item)
                <x-portfolio.card :item="$item" :link-label="$content['item_link_label'] ?? null" :aspect="$items->count() <= 2 ? 'aspect-[16/9] lg:aspect-[2/1]' : 'aspect-[16/10]'"/>
            @endforeach
        </div>
    </x-site.band>
@endif
