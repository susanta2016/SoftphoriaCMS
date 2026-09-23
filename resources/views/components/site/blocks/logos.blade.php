{{-- WEB-103 — Gallery "Logo strip": e.g. the homepage's "Trusted technologies" row. --}}
@props(['section', 'content', 'media'])

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null" class="!py-10 sm:!py-12">
    @if (!empty($content['eyebrow']))
        <p class="text-xs font-semibold tracking-[0.18em] text-brand-navy/45 uppercase">{{ $content['eyebrow'] }}</p>
    @endif
    <ul class="mt-6 grid grid-cols-2 items-center gap-x-6 gap-y-8 sm:grid-cols-4 lg:flex lg:flex-wrap lg:justify-between">
        @foreach ($content['gallery_items'] ?? [] as $item)
            @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
            <li class="flex items-center justify-center gap-2 lg:justify-start">
                @if ($itemMedia)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="{{ $item['title'] ?? $itemMedia->alt_text }}" class="h-8 w-auto max-w-[140px] object-contain" loading="lazy">
                @else
                    <x-site.icon :name="$item['icon'] ?? null" class="h-7 w-7 shrink-0"/>
                    @if (!empty($item['title']))
                        <span class="text-base font-bold tracking-tight sm:text-lg text-brand-navy/80">{{ $item['title'] }}</span>
                    @endif
                @endif
            </li>
        @endforeach
    </ul>
</x-site.band>
