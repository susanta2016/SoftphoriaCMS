{{-- WEB-103 — Cta "Banner" style: the redesigned homepage's full-width dark closing band. --}}
@props(['section', 'content'])

@php
    $background = !empty($content['background_media_id']) ? \App\Models\Media::find($content['background_media_id']) : null;
    $backgroundUrl = $background ? \Illuminate\Support\Facades\Storage::disk($background->disk)->url($background->path) : null;
@endphp

<section
    @if (!empty($content['anchor'])) id="{{ $content['anchor'] }}" @endif
    class="relative isolate scroll-mt-24 overflow-hidden bg-brand-navy bg-cover bg-bottom py-16 sm:py-20"
    @style([$backgroundUrl ? "background-image: url('{$backgroundUrl}')" : ''])
>
    @if ($backgroundUrl)
        {{-- Darker on the text side so the copy stays readable over any photo; lighter on the right to keep the image visible. --}}
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-navy/90 via-brand-navy/70 to-brand-navy/45" aria-hidden="true"></div>
    @endif
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:gap-12 lg:px-8">
        <div class="max-w-2xl">
            @if (!empty($content['eyebrow']))
                <p class="text-xs font-semibold tracking-[0.18em] text-brand-accent-light uppercase">{{ $content['eyebrow'] }}</p>
            @endif
            @if (!empty($content['heading']))
                <h2 class="mt-3 text-[1.75rem] leading-tight font-bold tracking-tight whitespace-pre-line text-white sm:text-3xl lg:text-[2.25rem]">{{ $content['heading'] }}</h2>
            @endif
            @if (!empty($content['description']))
                <p class="mt-3 text-base leading-relaxed text-white/80 sm:text-lg">{{ $content['description'] }}</p>
            @endif
        </div>
        <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap">
            @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                <x-site.button :href="$content['cta_url']" size="lg">
                    {{ $content['cta_label'] }} <x-site.arrow class="h-4 w-4"/>
                </x-site.button>
            @endif
            @if (!empty($content['secondary_cta_label']) && !empty($content['secondary_cta_url']))
                <x-site.button :href="$content['secondary_cta_url']" variant="outline-light" size="lg">
                    {{ $content['secondary_cta_label'] }}
                </x-site.button>
            @endif
        </div>
    </div>
</section>
