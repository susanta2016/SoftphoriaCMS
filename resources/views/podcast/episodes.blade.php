@php
    use Illuminate\Support\Facades\Storage;

    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
@endphp

<x-layouts.site :seo="$seo">
    <div
        @class(['relative overflow-hidden bg-cover bg-[position:50%_22%] bg-no-repeat pt-28 pb-12 sm:pt-32', 'bg-brand-ivory' => ! $bannerUrl])
        @style([$bannerUrl ? "background-image: linear-gradient(to right, rgba(251,243,230,.97) 0%, rgba(251,243,230,.94) 30%, rgba(251,243,230,.55) 58%, rgba(251,243,230,.15) 78%), url('{$bannerUrl}')" : ''])
    >
        <x-site.header :transparent="(bool) $bannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Podcast', 'url' => route('podcast.index')],
                ['label' => 'All Episodes'],
            ]"/>

            <h1 class="mt-4 font-serif text-4xl text-brand-navy sm:text-5xl">All Episodes</h1>
            <div class="mt-5 flex items-center gap-3" aria-hidden="true">
                <span class="h-px w-14 bg-brand-gold/70"></span>
                <span class="text-brand-gold">✦</span>
            </div>
            @if ($podcast?->description)
                <p class="mt-5 max-w-2xl text-sm leading-relaxed text-brand-navy/70">{{ str($podcast->description)->stripTags()->limit(220) }}</p>
            @endif
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-podcast-episodes-region>
                @include('podcast.partials.episodes-results')
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
