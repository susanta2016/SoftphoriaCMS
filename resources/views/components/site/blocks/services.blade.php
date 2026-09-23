{{-- WEB-103 — Gallery "Icon cards": e.g. the homepage's six service cards. --}}
@props(['section', 'content', 'media'])

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <x-site.section-heading
        :eyebrow="$content['eyebrow'] ?? null"
        :heading="$content['heading'] ?? null"
        :description="$content['description'] ?? null"
        :link-label="$content['link_label'] ?? null"
        :link-url="$content['link_url'] ?? null"
    />

    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($content['gallery_items'] ?? [] as $item)
            @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
            <div class="flex gap-4 rounded-xl border border-brand-navy/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-sky text-brand-accent">
                    @if ($itemMedia)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="" class="h-7 w-7 object-contain" loading="lazy">
                    @else
                        <x-site.icon :name="$item['icon'] ?? null" class="h-7 w-7"/>
                    @endif
                </span>
                <div class="min-w-0">
                    @if (!empty($item['title']))
                        <h3 class="font-semibold text-brand-navy">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mt-1.5 text-sm leading-relaxed text-brand-navy/65">{{ $item['description'] }}</p>
                    @endif
                    @if (!empty($item['url']) && !empty($content['item_link_label']))
                        <a href="{{ $item['url'] }}" class="group mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-accent transition hover:text-brand-accent-dark">
                            {{ $content['item_link_label'] }}<span class="sr-only">: {{ $item['title'] ?? '' }}</span>
                            <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"/>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-site.band>
