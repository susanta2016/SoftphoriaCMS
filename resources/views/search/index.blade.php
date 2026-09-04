<x-layouts.site :seo="$seo">
    <div class="relative overflow-hidden bg-brand-ivory pt-28 pb-10 sm:pt-32">
        <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">Search</span>
            <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">
                @if ($query !== '')
                    Results for &ldquo;{{ $query }}&rdquo;
                @else
                    Search All The Things Light
                @endif
            </h1>

            <form method="GET" action="{{ route('search.index') }}" class="mt-6 max-w-xl" data-search-page-form>
                <label for="search-page-input" class="sr-only">Search</label>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-brand-navy/40"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/></svg>
                    <input
                        id="search-page-input"
                        type="search"
                        name="q"
                        value="{{ $query }}"
                        placeholder="Search Music, Light Posts, Inspirational Resources, Community…"
                        class="w-full rounded-md border border-brand-navy/20 bg-white py-2.5 pr-3 pl-9 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div data-search-region>
                @include('search.partials.results')
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
