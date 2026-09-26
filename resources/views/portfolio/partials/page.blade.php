{{--
    The /portfolio page body — also the async region the category chips
    swap in place (see PortfolioController and [data-async-region] in
    resources/js/app.js).
--}}
<div data-async-region="portfolio" data-page-title="{{ $seo['title'] }}">
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark pt-28 pb-16 text-white sm:pt-36 sm:pb-20">
        <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <x-blog.breadcrumbs :items="[['label' => 'Portfolio', 'url' => route('portfolio.index')]]" :dark="true"/>

            <p class="mt-8 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-white/85 uppercase backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                Portfolio
            </p>
            <h1 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl">Selected projects</h1>
            <p class="mt-4 max-w-2xl text-lg leading-relaxed text-white/75">A look at the websites, platforms and integrations we've built for our clients.</p>

            @if ($categories->count() > 1)
                <nav aria-label="Filter projects" class="mt-10 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <ul class="flex w-max gap-2 sm:w-auto sm:flex-wrap">
                        <li>
                            <a href="{{ route('portfolio.index') }}" data-async-link @class([
                                'inline-flex rounded-full px-4 py-2 text-sm font-medium transition',
                                'bg-white text-brand-navy' => $active === '',
                                'border border-white/20 text-white/85 hover:border-white/50 hover:text-white' => $active !== '',
                            ])>All projects</a>
                        </li>
                        @foreach ($categories as $category)
                            <li>
                                <a href="{{ route('portfolio.index', ['category' => $category]) }}" data-async-link @class([
                                    'inline-flex rounded-full px-4 py-2 text-sm font-medium transition',
                                    'bg-white text-brand-navy' => $active === $category,
                                    'border border-white/20 text-white/85 hover:border-white/50 hover:text-white' => $active !== $category,
                                ])>{{ $category }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </section>

    <section id="articles" class="scroll-mt-24 bg-brand-mist py-14 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6" aria-live="polite">
            @if ($items->isEmpty())
                <p class="rounded-3xl border border-dashed border-brand-navy/15 bg-white px-6 py-16 text-center text-brand-navy/60">New projects are on their way — please check back soon.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
                        <x-portfolio.card :item="$item" link-label="View project" heading-level="h2"/>
                    @endforeach
                </div>
            @endif

            <div class="mt-14 flex flex-col items-start justify-between gap-6 rounded-3xl bg-white p-8 shadow-sm sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-xl font-bold text-brand-navy">Have a project like these in mind?</h2>
                    <p class="mt-1 text-brand-navy/65">Tell us about it — we'll reply with practical next steps.</p>
                </div>
                <a href="{{ route('contact.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-accent-dark">
                    Start a project <x-site.arrow class="h-4 w-4"/>
                </a>
            </div>
        </div>
    </section>
</div>
