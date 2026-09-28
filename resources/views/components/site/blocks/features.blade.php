{{--
    WEB-103 — Gallery "Intro + features": e.g. the homepage's "Why
    Softphoria?" block — heading/description and a button on the left, a
    row of icon items on the right. The section's header link doubles as
    that button here.
--}}
@props(['section', 'content', 'media'])

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <div class="grid gap-12 lg:grid-cols-12 lg:items-center lg:gap-12">
        <div class="lg:col-span-4">
            @if (!empty($content['eyebrow']))
                <p class="text-xs font-semibold tracking-[0.18em] text-brand-accent uppercase">{{ $content['eyebrow'] }}</p>
            @endif
            @if (!empty($content['heading']))
                <h2 class="mt-3 text-[1.75rem] leading-tight font-bold tracking-tight whitespace-pre-line text-brand-navy sm:text-3xl lg:text-[2.25rem]">{{ $content['heading'] }}</h2>
            @endif
            @if (!empty($content['description']))
                <p class="mt-4 text-base leading-relaxed text-brand-muted">{{ $content['description'] }}</p>
            @endif
            @if (!empty($content['link_label']) && !empty($content['link_url']))
                <x-site.button :href="$content['link_url']" class="mt-6">
                    {{ $content['link_label'] }} <x-site.arrow class="h-4 w-4"/>
                </x-site.button>
            @endif
        </div>

        <ul class="grid gap-x-6 gap-y-10 min-[420px]:grid-cols-2 md:grid-cols-4 lg:col-span-8">
            @foreach ($content['gallery_items'] ?? [] as $item)
                @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
                <li class="text-center">
                    <span class="inline-flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-brand-sky text-brand-accent ring-1 ring-brand-accent/15" aria-hidden="true">
                        @if ($itemMedia)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="" class="h-9 w-9 object-contain" loading="lazy">
                        @else
                            <x-site.icon :name="$item['icon'] ?? null" class="h-9 w-9"/>
                        @endif
                    </span>
                    @if (!empty($item['title']))
                        <h3 class="mt-5 text-xl font-bold tracking-tight text-brand-navy">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mx-auto mt-2.5 max-w-[15rem] text-[0.9375rem] leading-7 text-brand-muted">{{ $item['description'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</x-site.band>
