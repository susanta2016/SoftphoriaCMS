{{--
    WEB-103 — Gallery "Logo strip": e.g. the homepage's "Trusted technologies"
    row, auto-scrolling horizontally as a seamless loop (see .logo-marquee in
    app.css). The list is rendered twice; the second copy is aria-hidden so
    screen readers announce each logo once.
--}}
@props(['section', 'content', 'media'])

@php
    $items = $content['gallery_items'] ?? [];
    // Each copy must be at least as wide as the band or a gap shows before
    // the loop restarts, so a short list is repeated within each copy.
    if ($items) {
        $items = array_merge(...array_fill(0, (int) ceil(8 / count($items)), $items));
    }
    // Roughly constant speed whatever the item count.
    $duration = max(20, count($items) * 4);
@endphp

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null" class="!py-10 sm:!py-12">
    @if (!empty($content['eyebrow']))
        <p class="text-xs font-semibold tracking-[0.18em] text-brand-navy/45 uppercase">{{ $content['eyebrow'] }}</p>
    @endif

    @if ($items)
        <div class="logo-marquee mt-6 overflow-hidden" style="--marquee-duration: {{ $duration }}s">
            <div class="logo-marquee-track flex w-max">
                @foreach ([false, true] as $isCopy)
                    {{-- pr matches gap, so the spacing across the copy boundary equals the spacing between items. --}}
                    <ul class="flex shrink-0 items-center gap-x-12 gap-y-6 pr-12 sm:gap-x-16 sm:pr-16" @if ($isCopy) aria-hidden="true" @endif>
                        @foreach ($items as $item)
                            @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
                            <li class="flex shrink-0 items-center gap-2">
                                @if ($itemMedia)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="{{ $isCopy ? '' : ($item['title'] ?? $itemMedia->alt_text) }}" class="h-8 w-auto max-w-[140px] object-contain" loading="lazy">
                                @else
                                    <x-site.icon :name="$item['icon'] ?? null" class="h-7 w-7 shrink-0"/>
                                    @if (!empty($item['title']))
                                        <span class="text-base font-bold tracking-tight whitespace-nowrap sm:text-lg text-brand-navy/80">{{ $item['title'] }}</span>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    @endif
</x-site.band>
