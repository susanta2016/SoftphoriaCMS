<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-brand-ivory pt-28 pb-10 sm:pt-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-xl">
                <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">Inspirational Resources</span>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">Stories that awaken and inspire.</h1>
                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                </div>
                <p class="max-w-xl text-base leading-relaxed text-brand-navy/75">
                    Has a song, an album, or a moment of reflection touched your life in a meaningful way?
                    Explore the stories, testimonies, and reflections shared by our community.
                </p>
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
