{{--
    WEB-103 — Gallery "Project cards": e.g. the homepage's "Selected
    projects". Item title is the small category label, description the
    card text. With no image uploaded yet, a branded placeholder (the
    item's icon on a navy-to-blue gradient) keeps the card shape.
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

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($content['gallery_items'] ?? [] as $item)
            @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
            <article class="flex flex-col overflow-hidden rounded-xl border border-brand-navy/10 bg-white shadow-sm transition hover:shadow-md">
                @if ($itemMedia)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="{{ $itemMedia->alt_text ?: '' }}" class="aspect-[16/10] w-full object-cover" loading="lazy">
                @else
                    <div class="flex aspect-[16/10] w-full items-center justify-center bg-gradient-to-br from-brand-navy to-brand-accent text-white/85" aria-hidden="true">
                        <x-site.icon :name="($item['icon'] ?? null) ?: 'monitor'" :mono="true" class="h-14 w-14"/>
                    </div>
                @endif
                <div class="flex flex-1 flex-col p-5 sm:p-6">
                    @if (!empty($item['title']))
                        <h3 class="text-xs font-semibold tracking-[0.12em] text-brand-accent uppercase">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mt-2 text-sm leading-relaxed text-brand-navy/80">{{ $item['description'] }}</p>
                    @endif
                    @if (!empty($item['url']) && !empty($content['item_link_label']))
                        <a href="{{ $item['url'] }}" class="group mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-brand-accent transition hover:text-brand-accent-dark">
                            {{ $content['item_link_label'] }}<span class="sr-only">: {{ $item['title'] ?? '' }}</span>
                            <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"/>
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-site.band>
