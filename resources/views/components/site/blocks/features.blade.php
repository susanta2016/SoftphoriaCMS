{{--
    WEB-103 — Gallery "Intro + features": e.g. the homepage's "Why
    Softphoria?" block — heading/description and a button on the left, a
    row of icon items on the right. The section's header link doubles as
    that button here.
--}}
@props(['section', 'content', 'media'])

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <div class="grid gap-10 lg:grid-cols-12 lg:gap-12">
        <div class="lg:col-span-4">
            @if (!empty($content['eyebrow']))
                <p class="text-xs font-semibold tracking-[0.18em] text-brand-accent uppercase">{{ $content['eyebrow'] }}</p>
            @endif
            @if (!empty($content['heading']))
                <h2 class="mt-2 text-2xl leading-tight font-bold whitespace-pre-line text-brand-navy sm:text-3xl">{{ $content['heading'] }}</h2>
            @endif
            @if (!empty($content['description']))
                <p class="mt-4 text-sm leading-relaxed text-brand-navy/70 sm:text-base">{{ $content['description'] }}</p>
            @endif
            @if (!empty($content['link_label']) && !empty($content['link_url']))
                <x-site.button :href="$content['link_url']" class="mt-6">
                    {{ $content['link_label'] }} <x-site.arrow class="h-4 w-4"/>
                </x-site.button>
            @endif
        </div>

        <ul class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4 lg:col-span-8 lg:pt-2">
            @foreach ($content['gallery_items'] ?? [] as $item)
                @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
                <li class="text-center">
                    <span class="inline-flex h-12 w-12 items-center justify-center text-brand-accent">
                        @if ($itemMedia)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="" class="h-10 w-10 object-contain" loading="lazy">
                        @else
                            <x-site.icon :name="$item['icon'] ?? null" class="h-10 w-10"/>
                        @endif
                    </span>
                    @if (!empty($item['title']))
                        <h3 class="mt-3 text-lg font-bold text-brand-navy">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mx-auto mt-2 max-w-[12rem] text-sm leading-relaxed text-brand-navy/65">{{ $item['description'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</x-site.band>
