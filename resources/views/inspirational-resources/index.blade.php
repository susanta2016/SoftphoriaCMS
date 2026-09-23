@php
    use Illuminate\Support\Facades\Storage;

    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
@endphp

<x-layouts.site :seo="$seo">
    {{-- Same hero treatment as poetry-prose/index.blade.php (see the
        comments there for the gradient, background-size, and mobile scrim
        reasoning); the banner image and copy come from Website Setup →
        Settings → Inspirational Resources. Unlike Poetry/Prose's tuned
        photo, the banner here is whatever the admin uploads, so it uses a
        plain cover fit rather than Poetry/Prose's photo-specific zoom. --}}
    <div
        @class(['relative overflow-hidden bg-no-repeat', 'bg-brand-ivory' => ! $bannerUrl])
        @style([$bannerUrl
            ? "background-image: linear-gradient(to right, rgba(251,243,230,.97) 0%, rgba(251,243,230,.94) 30%, rgba(251,243,230,.55) 58%, rgba(251,243,230,.15) 78%), url('{$bannerUrl}'); background-size: cover; background-position: center;"
            : ''])
    >
        @if ($bannerUrl)
            <div class="absolute inset-0 bg-brand-ivory/95 sm:hidden" aria-hidden="true"></div>
        @endif

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
            <div data-inspirational-resources-results-region>
                @include('inspirational-resources.partials.results')
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
