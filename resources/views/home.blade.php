{{--
    WEB-103 — the redesigned Softphoria homepage. The Hero keeps its own
    bespoke markup (a light two-column band: copy, buttons and stats on
    the left, the hero image on the right — stacked on phones); every other
    section renders through the same x-site.sections component the rest of
    the public site uses, so there's no second copy of that rendering
    logic. See HomeController.
--}}
@php
    $heroImageUrl = $hero['media'] ? \Illuminate\Support\Facades\Storage::disk($hero['media']->disk)->url($hero['media']->path) : null;
    $stats = collect($hero['stats'] ?? [])->filter(fn ($stat) => filled($stat['value'] ?? null) && filled($stat['label'] ?? null));
@endphp

<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
    {{--
        Matched to the reference design: a flat pale ice-blue band, the hero
        image bleeding off the right edge from lg up (below the
        copy, full-width, on smaller screens), feathered into the band on its
        left via .hero-image-fade. The band is deliberately a shade deeper
        than the reference so it reads as clearly separate from the white
        "Trusted technologies" strip below (no fade into white).
    --}}
    <div class="relative isolate overflow-hidden border-b border-brand-navy/5 bg-gradient-to-br from-brand-ice to-brand-ice-deep">
        <div class="relative z-10 mx-auto grid max-w-7xl items-center px-4 pt-28 pb-8 sm:px-6 sm:pt-32 lg:min-h-[38rem] lg:grid-cols-12 lg:px-8 lg:pt-24 lg:pb-16">
            <div class="lg:col-span-5">
                @if ($hero['eyebrow'])
                    <p class="text-xs font-semibold tracking-[0.2em] text-brand-navy/70 uppercase">{{ $hero['eyebrow'] }}</p>
                @endif
                <h1 class="mt-3 text-4xl leading-[1.08] font-extrabold tracking-tight text-brand-navy [text-wrap:balance] sm:text-5xl xl:text-[3.5rem]">
                    <span class="whitespace-pre-line">{{ $hero['heading'] }}</span>
                    @if ($hero['heading_highlight'])
                        <span class="block text-brand-accent">{{ $hero['heading_highlight'] }}</span>
                    @endif
                </h1>

                @if ($hero['subheading'])
                    <p class="mt-5 max-w-xl text-base leading-relaxed whitespace-pre-line text-brand-navy/75">{{ $hero['subheading'] }}</p>
                @endif

                @if (($hero['cta_label'] && $hero['cta_url']) || ($hero['secondary_cta_label'] && $hero['secondary_cta_url']))
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        @if ($hero['cta_label'] && $hero['cta_url'])
                            <x-site.button :href="$hero['cta_url']" size="lg">
                                {{ $hero['cta_label'] }} <x-site.arrow class="h-4 w-4"/>
                            </x-site.button>
                        @endif
                        @if ($hero['secondary_cta_label'] && $hero['secondary_cta_url'])
                            <x-site.button :href="$hero['secondary_cta_url']" variant="outline" size="lg" class="sm:min-w-40">
                                {{ $hero['secondary_cta_label'] }}
                            </x-site.button>
                        @endif
                    </div>
                @endif

                @if ($hero['tertiary_label'])
                    @php $playIcon = '<span class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-brand-accent/60 text-brand-accent"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5"><path d="M8 5v14l11-7z"/></svg></span>'; @endphp
                    @if ($hero['tertiary_video'] || $hero['tertiary_embed_url'])
                        <button type="button" data-video-modal-toggle class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-brand-navy transition hover:text-brand-accent">
                            {!! $playIcon !!} {{ $hero['tertiary_label'] }}
                        </button>
                    @elseif ($hero['tertiary_url'])
                        <a href="{{ $hero['tertiary_url'] }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-brand-navy transition hover:text-brand-accent">
                            {!! $playIcon !!} {{ $hero['tertiary_label'] }}
                        </a>
                    @endif
                @endif

                @if ($stats->isNotEmpty())
                    <dl class="mt-9 grid grid-cols-3 gap-4 sm:max-w-md sm:gap-8">
                        @foreach ($stats as $stat)
                            <div class="flex flex-col-reverse">
                                <dt class="mt-1 text-xs leading-snug text-brand-navy/60">{{ $stat['label'] }}</dt>
                                <dd class="text-lg font-bold whitespace-nowrap text-brand-navy sm:text-2xl">{{ $stat['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>

        @if ($heroImageUrl)
            {{--
                From lg up: pinned to the right edge and vertically centred in
                the space below the fixed header, at its natural aspect ratio
                (width-capped) so the artwork's cards and script are never
                cropped at any desktop width.
            --}}
            <div class="hero-image-fade lg:absolute lg:top-[calc(50%+2.125rem)] lg:right-0 lg:w-[58%] lg:max-w-[56rem] lg:-translate-y-1/2">
                <img
                    src="{{ $heroImageUrl }}"
                    alt="{{ $hero['media']->alt_text ?: '' }}"
                    class="h-auto w-full"
                    fetchpriority="high"
                >
            </div>
        @endif
    </div>

    @if ($sections->isNotEmpty())
        <x-site.sections :sections="$sections"/>
    @endif
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>

    @if ($hero['tertiary_video'] || $hero['tertiary_embed_url'])
        <div data-video-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
            <div class="relative w-full max-w-3xl">
                <button type="button" data-video-modal-close aria-label="Close video" class="absolute -top-10 right-0 text-white transition hover:text-brand-accent-light">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                @if ($hero['tertiary_video'])
                    <video data-video-modal-player controls playsinline preload="none" class="aspect-video w-full rounded-lg bg-black">
                        <source src="{{ route('media.watch', $hero['tertiary_video']) }}" type="{{ $hero['tertiary_video']->mime_type }}">
                    </video>
                @else
                    <iframe
                        data-video-modal-iframe
                        data-src="{{ $hero['tertiary_embed_url'] }}"
                        class="aspect-video w-full rounded-lg bg-black"
                        allow="autoplay; fullscreen; picture-in-picture"
                        allowfullscreen
                        frameborder="0"
                    ></iframe>
                @endif
            </div>
        </div>
    @endif
</x-layouts.site>
