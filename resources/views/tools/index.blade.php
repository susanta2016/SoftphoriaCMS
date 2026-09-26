{{--
    /tools — the Tools hub: hero, category filter, featured tools and all
    published tools, with instant search (resources/js/tools/hub.js). Built
    entirely from live tools; categories without one are not shown.
--}}
<x-layouts.site :seo="$seo">
    @if ($tools->isNotEmpty())
        <x-slot:head>
            @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
                @vite(['resources/js/tools/hub.js'])
            @endif
        </x-slot:head>
    @endif

    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1" data-tools-hub>
        {{-- Hero --}}
        <header class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark pt-32 pb-16 text-white sm:pt-40 sm:pb-20">
            <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-[28rem] w-[28rem] rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <x-blog.breadcrumbs :items="[['label' => 'Tools', 'url' => route('tools.index')]]" :dark="true"/>
                <h1 class="mt-6 max-w-3xl text-4xl leading-tight font-bold tracking-tight sm:text-5xl">{{ $settings->get('title') }}</h1>
                @if ($settings->get('intro'))
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/75">{{ $settings->get('intro') }}</p>
                @endif

                @if ($tools->count() > 1)
                    <form class="mt-8 max-w-xl" role="search" data-tools-search-form>
                        <label for="tools-search" class="sr-only">Search tools</label>
                        <div class="flex items-center gap-3 rounded-2xl bg-white px-4 py-1 text-brand-navy shadow-xl focus-within:ring-2 focus-within:ring-brand-accent-light">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 shrink-0 text-brand-navy/40" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
                            <input id="tools-search" type="search" placeholder="Search tools, e.g. calculator, SEO…" autocomplete="off"
                                   class="w-full border-0 bg-transparent py-3 text-base placeholder:text-brand-navy/40 focus:ring-0 focus:outline-none" data-tools-search>
                        </div>
                    </form>
                @endif
            </div>
        </header>

        @if ($tools->isEmpty())
            <section class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6">
                <h2 class="text-2xl font-bold text-brand-navy">New tools are on the way</h2>
                <p class="mt-3 text-brand-navy/70">We're preparing our first free tools. In the meantime, <a href="{{ route('contact.index') }}" class="font-semibold text-brand-accent hover:text-brand-accent-dark">tell us what you're working on</a>.</p>
            </section>
        @else
            {{-- Featured --}}
            @if ($featured->isNotEmpty() && $tools->count() > $featured->count())
                <section class="bg-white pt-16" aria-labelledby="featured-heading" data-tools-featured>
                    <div class="mx-auto max-w-6xl px-4 sm:px-6">
                        <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">Featured</p>
                        <h2 id="featured-heading" class="mt-2 text-2xl font-bold tracking-tight text-brand-navy sm:text-3xl">Popular tools</h2>
                        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($featured as $tool)
                                <x-tool.card :tool="$tool" class="border-brand-accent/20 bg-gradient-to-b from-brand-sky/60 to-white"/>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- All tools, with category filter --}}
            <section class="bg-white py-16 sm:py-20" aria-labelledby="all-tools-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <h2 id="all-tools-heading" class="text-2xl font-bold tracking-tight text-brand-navy sm:text-3xl">All tools</h2>
                        @if ($categories->count() > 1)
                            <div role="group" aria-label="Filter by category">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" aria-pressed="true" data-tools-category=""
                                            class="rounded-full border border-brand-navy/15 px-4 py-2 text-sm font-semibold text-brand-navy/75 transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none aria-pressed:border-brand-accent aria-pressed:bg-brand-accent aria-pressed:text-white">
                                        All <span class="opacity-70">{{ $tools->count() }}</span>
                                    </button>
                                    @foreach ($categories as $category)
                                        <button type="button" aria-pressed="false" data-tools-category="{{ $category->slug }}"
                                                class="rounded-full border border-brand-navy/15 px-4 py-2 text-sm font-semibold text-brand-navy/75 transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none aria-pressed:border-brand-accent aria-pressed:bg-brand-accent aria-pressed:text-white">
                                            {{ $category->name }} <span class="opacity-70">{{ $tools->where('tool_category_id', $category->id)->count() }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <p class="sr-only" aria-live="polite" data-tools-status></p>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-tools-list>
                        @foreach ($tools as $tool)
                            <x-tool.card :tool="$tool" heading-level="h3"/>
                        @endforeach
                    </div>
                    <p class="mt-8 rounded-2xl bg-brand-mist p-6 text-center text-brand-navy/70" hidden data-tools-empty>
                        No tools match your search. <a href="{{ route('contact.index') }}" class="font-semibold text-brand-accent hover:text-brand-accent-dark">Suggest a tool</a>
                    </p>
                </div>
            </section>
        @endif

        <x-tool.cta :cta="['heading' => $settings->get('cta_heading'), 'text' => $settings->get('cta_text'), 'label' => $settings->get('cta_label'), 'url' => $settings->get('cta_url')]"/>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
