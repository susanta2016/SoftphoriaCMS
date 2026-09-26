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
    $items = \App\Models\PortfolioItem::query()
        ->with('cover')
        ->published()
        ->featured()
        ->ordered()
        ->limit(max(1, min(12, (int) ($content['limit'] ?? 6))))
        ->get();
@endphp

@if ($items->isNotEmpty())
    <x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
        <x-site.section-heading
            :eyebrow="$content['eyebrow'] ?? null"
            :heading="$content['heading'] ?? null"
            :description="$content['description'] ?? null"
            :link-label="$content['link_label'] ?? null"
            :link-url="$content['link_url'] ?? null"
        />

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                @php $external = $item->link_url && str_starts_with($item->link_url, 'http'); @endphp
                <article class="group flex flex-col overflow-hidden rounded-xl border border-brand-navy/10 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="overflow-hidden">
                        @if ($item->cover)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->cover->disk)->url($item->cover->path) }}" alt="{{ $item->cover->alt_text ?: $item->title }}" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
                        @else
                            <div class="flex aspect-[16/10] w-full items-center justify-center bg-gradient-to-br from-brand-navy to-brand-accent text-white/85" aria-hidden="true">
                                <x-site.icon :name="$item->icon ?: 'monitor'" :mono="true" class="h-14 w-14"/>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-5 sm:p-6">
                        @if ($item->category)
                            <p class="text-xs font-semibold tracking-[0.12em] text-brand-accent uppercase">{{ $item->category }}</p>
                        @endif
                        <h3 @class(['text-lg font-bold text-brand-navy', 'mt-1.5' => $item->category])>{{ $item->title }}</h3>
                        @if ($item->summary)
                            <p class="mt-2 text-sm leading-relaxed text-brand-navy/75">{{ $item->summary }}</p>
                        @endif
                        @if (! empty($item->technologies))
                            <ul class="mt-4 flex flex-wrap gap-1.5" aria-label="Technologies">
                                @foreach ($item->technologies as $technology)
                                    <li class="rounded-full bg-brand-sky px-2.5 py-0.5 text-xs font-medium text-brand-navy/75">{{ $technology }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($item->link_url && ! empty($content['item_link_label']))
                            <a
                                href="{{ $item->link_url }}"
                                @if ($external) target="_blank" rel="noopener noreferrer" @endif
                                class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-brand-accent transition hover:text-brand-accent-dark"
                            >
                                {{ $content['item_link_label'] }}<span class="sr-only">: {{ $item->title }}</span>
                                <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"/>
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </x-site.band>
@endif
