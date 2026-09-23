{{--
    WEB-103 — Gallery "Article cards": e.g. the homepage's "Latest
    insights". Item description is shown as the small date/meta line. With
    no image uploaded, the item's icon (e.g. a technology logo) is shown in
    white on a navy tile, matching the design's dark thumbnails.
--}}
@props(['section', 'content', 'media'])

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <x-site.section-heading
        :eyebrow="$content['eyebrow'] ?? null"
        :heading="$content['heading'] ?? null"
        :description="$content['description'] ?? null"
        :link-label="$content['link_label'] ?? null"
        :link-url="$content['link_url'] ?? null"
    />

    <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($content['gallery_items'] ?? [] as $item)
            @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
            <article class="flex gap-4 rounded-xl border border-brand-navy/10 bg-white p-3 shadow-sm transition hover:shadow-md">
                @if ($itemMedia)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="{{ $itemMedia->alt_text ?: '' }}" class="h-24 w-28 shrink-0 rounded-lg object-cover sm:w-32" loading="lazy">
                @else
                    <div class="flex h-24 w-28 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-navy-dark to-brand-navy text-white sm:w-32" aria-hidden="true">
                        <x-site.icon :name="($item['icon'] ?? null) ?: 'document'" :mono="true" class="h-10 w-10"/>
                    </div>
                @endif
                <div class="flex min-w-0 flex-col py-1">
                    @if (!empty($item['title']))
                        <h3 class="text-sm leading-snug font-semibold text-brand-navy">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mt-1 text-xs text-brand-navy/50">{{ $item['description'] }}</p>
                    @endif
                    @if (!empty($item['url']) && !empty($content['item_link_label']))
                        <a href="{{ $item['url'] }}" class="group mt-auto inline-flex items-center gap-1.5 pt-2 text-sm font-semibold text-brand-accent transition hover:text-brand-accent-dark">
                            {{ $content['item_link_label'] }}<span class="sr-only">: {{ $item['title'] ?? '' }}</span>
                            <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"/>
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-site.band>
