{{--
    WEB-102 — the Softphoria homepage. The Hero keeps its own bespoke
    full-bleed banner treatment (this look is specific to the homepage);
    every other section (Who We Are, Services, Expertise, Process,
    Testimonials, the Contact Form CTA — see HomePageSeeder) renders through
    the same x-site.sections component the rest of the public site uses, so
    there's no second copy of that rendering logic. See HomeController.

    The old hardcoded "Latest Community Comments" block (fake names/quotes)
    and the "Join Our Community" hero panel were Jacob/All The Things Light
    content with no Softphoria equivalent and have been removed, not
    replaced with placeholder content.
--}}
@php
    $bannerUrl = $hero['media'] ? \Illuminate\Support\Facades\Storage::disk($hero['media']->disk)->url($hero['media']->path) : null;
@endphp

<x-layouts.site :seo="$seo">
    <div class="relative isolate overflow-hidden bg-brand-navy">
        @if ($bannerUrl)
            <img
                src="{{ $bannerUrl }}"
                alt="{{ $hero['media']->alt_text ?: '' }}"
                class="absolute inset-0 z-0 h-full w-full object-cover"
            >
        @endif
        <x-site.header transparent :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative z-20 mx-auto max-w-7xl px-4 pt-32 pb-24 sm:px-6 lg:px-8 lg:pt-40 lg:pb-32">
            <div class="max-w-2xl">
                <h1 class="text-[2.5rem] leading-[1.15] font-serif text-brand-navy [text-wrap:balance] sm:text-5xl">
                    {{ $hero['heading'] }}
                </h1>

                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                </div>

                @if ($hero['subheading'])
                    <p class="max-w-xl text-base leading-relaxed whitespace-pre-line text-brand-navy/80 sm:text-lg">{{ $hero['subheading'] }}</p>
                @endif

                <div class="mt-8 flex flex-wrap items-center gap-4">
                    @if ($hero['cta_label'] && $hero['cta_url'])
                        <a href="{{ $hero['cta_url'] }}" class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-6 py-3.5 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                            {{ $hero['cta_label'] }}
                        </a>
                    @endif

                    @if ($hero['secondary_cta_label'] && $hero['secondary_cta_url'])
                        <a href="{{ $hero['secondary_cta_url'] }}" class="inline-flex items-center rounded-md border border-brand-navy/40 px-6 py-3.5 text-sm font-semibold tracking-wide text-brand-navy uppercase transition hover:border-brand-navy hover:bg-white/40">
                            {{ $hero['secondary_cta_label'] }}
                        </a>
                    @endif
                </div>

                @if ($hero['tertiary_label'])
                    @if ($hero['tertiary_video'] || $hero['tertiary_embed_url'])
                        <button type="button" data-video-modal-toggle class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-navy transition hover:text-brand-gold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-brand-navy/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                            {{ $hero['tertiary_label'] }}
                        </button>
                    @else
                        <a href="{{ $hero['tertiary_url'] }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-navy transition hover:text-brand-gold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-brand-navy/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                            {{ $hero['tertiary_label'] }}
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @if ($sections->isNotEmpty())
        <x-site.sections :sections="$sections"/>
    @endif

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>

    @if ($hero['tertiary_video'] || $hero['tertiary_embed_url'])
        <div data-video-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
            <div class="relative w-full max-w-3xl">
                <button type="button" data-video-modal-close aria-label="Close video" class="absolute -top-10 right-0 text-white transition hover:text-brand-gold">
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
