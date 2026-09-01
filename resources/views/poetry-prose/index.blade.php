@php
    use Illuminate\Support\Facades\Storage;

    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
@endphp

<x-layouts.site :seo="$seo">
    {{--
        Plain bg-cover/bg-center put the banner's lighthouse right behind
        the fixed header on common viewport widths — same underlying cause
        as the detail page's hero (see the comment there): the source
        photo's lighthouse sits close enough to the top of the frame that
        a full-bleed cover fit leaves too little sky margin above it once
        the header is accounted for. Zooming in past cover (165% of the
        container's own height, vs. ~107% for a plain cover fit at this
        hero's typical height) pushes the visible lighthouse down far
        enough to clear the header with real margin. Position-x is pinned
        to the image's actual right edge (100%, not an in-between guess)
        so the lighthouse keeps its full surrounding context — cottage,
        cliff path, fence — matching the approved reference instead of
        being cropped tight against the viewport edge; 100% also stays
        correct across viewport widths since it's anchored to the image's
        own edge rather than a width-dependent fraction. Verified
        pixel-by-pixel in-browser, not just by eye.
    --}}
    <div
        @class(['relative overflow-hidden bg-no-repeat', 'bg-brand-ivory' => ! $bannerUrl])
        @style([$bannerUrl
            ? "background-image: linear-gradient(to right, rgba(251,243,230,.97) 0%, rgba(251,243,230,.94) 30%, rgba(251,243,230,.55) 58%, rgba(251,243,230,.15) 78%), url('{$bannerUrl}'); background-size: auto 165%; background-position: 100% 0%;"
            : ''])
    >
        <x-site.header :transparent="(bool) $bannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 pt-32 pb-24 sm:px-6 lg:px-8 lg:pt-40 lg:pb-32">
            <div class="max-w-xl">
                <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">{{ $heroEyebrow }}</span>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">{{ $heroHeading }}</h1>
                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                </div>
                @if ($heroDescription)
                    <p class="max-w-xl text-base leading-relaxed text-brand-navy/75">{{ $heroDescription }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-poetry-prose-results-region>
                @include('poetry-prose.partials.results')
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
