{{--
    /portfolio/{slug} — one portfolio project, the same template for every
    item (see PortfolioController::show). Built from the site's existing
    pieces: the Portfolio listing's dark hero band, breadcrumbs, eyebrow
    pill, rounded cards, technology chips and the shared portfolio CTA.

    Everything is CMS content: the Challenge / Solution / Outcome sections,
    services, technologies, gallery and "Visit project" button each render
    only when an admin has filled them in — no empty headings, no
    generated copy. With no featured image a branded project graphic is
    shown instead (never a stand-in "screenshot").
--}}
<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
        @if ($isPreview)
            <div class="fixed inset-x-0 bottom-0 z-40 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950" role="status">
                Preview — this project is unpublished and not visible to the public.
            </div>
        @endif

        {{-- Project hero --}}
        <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark pt-28 pb-16 text-white sm:pt-36 sm:pb-20">
            <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <x-blog.breadcrumbs :items="[['label' => 'Portfolio', 'url' => route('portfolio.index')], ['label' => $item->title, 'url' => $item->url()]]" :dark="true"/>

                <div class="mt-8 grid items-center gap-10 lg:grid-cols-12 lg:gap-12">
                    <div class="min-w-0 lg:col-span-6">
                        @if ($item->category)
                            <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-white/85 uppercase backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                {{ $item->category }}
                            </p>
                        @endif
                        <h1 @class(['text-4xl leading-tight font-bold tracking-tight [overflow-wrap:anywhere] sm:text-5xl', 'mt-5' => $item->category])>{{ $item->title }}</h1>
                        @if ($item->summary)
                            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/75">{{ $item->summary }}</p>
                        @endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            @if ($item->link_url)
                                <x-site.button :href="$item->link_url" size="lg" variant="light" class="shadow-lg" :target="$item->isExternalLink() ? '_blank' : null" :rel="$item->isExternalLink() ? 'noopener noreferrer' : null">
                                    Visit project <x-site.arrow class="h-4 w-4"/>
                                    @if ($item->isExternalLink())<span class="sr-only">(opens in a new tab)</span>@endif
                                </x-site.button>
                            @endif
                            <x-site.button :href="route('portfolio.index')" variant="outline-light" size="lg">All projects</x-site.button>
                        </div>
                    </div>

                    <div class="lg:col-span-6">
                        <div class="relative">
                            <div class="absolute -inset-3 -z-10 rounded-[2rem] bg-gradient-to-br from-brand-accent-light/40 to-transparent blur-sm" aria-hidden="true"></div>
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ $item->cover->alt_text ?: $item->title }}" class="aspect-[16/10] w-full rounded-3xl bg-brand-navy object-cover shadow-2xl shadow-black/30" fetchpriority="high">
                            @else
                                <div class="flex aspect-[16/10] w-full items-center justify-center rounded-3xl border border-white/10 bg-gradient-to-br from-brand-navy via-brand-navy-mid to-brand-royal text-white/85 shadow-2xl shadow-black/30" aria-hidden="true">
                                    <x-site.icon :name="$item->icon ?: 'monitor'" :mono="true" class="h-20 w-20"/>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Project overview (CMS-approved sections only) + services & technologies --}}
        @if ($sections->isNotEmpty() || $item->services->isNotEmpty() || filled($item->technologies))
            <section class="bg-white py-16 sm:py-20" aria-label="Project overview">
                <div @class(['mx-auto max-w-6xl px-4 sm:px-6', 'grid gap-12 lg:grid-cols-12' => $sections->isNotEmpty()])>
                    @if ($sections->isNotEmpty())
                        <div class="min-w-0 space-y-12 lg:col-span-8">
                            @foreach ($sections as $heading => $html)
                                <div>
                                    <p class="text-xs font-semibold tracking-[0.18em] text-brand-accent uppercase">{{ sprintf('%02d', $loop->iteration) }}</p>
                                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-brand-navy sm:text-3xl">{{ $heading }}</h2>
                                    <div class="blog-prose mt-4">{!! $html !!}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <aside @class([
                        'space-y-6 lg:col-span-4' => $sections->isNotEmpty(),
                        'grid gap-6' => $sections->isEmpty(),
                        'md:grid-cols-2' => $sections->isEmpty() && $item->services->isNotEmpty() && filled($item->technologies),
                    ]) aria-label="{{ $item->title }} at a glance">
                        @if ($item->services->isNotEmpty())
                            <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-xs shadow-brand-navy/5">
                                <h2 class="text-xs font-semibold tracking-[0.15em] text-brand-navy/60 uppercase">Services</h2>
                                <ul class="mt-4 space-y-2.5">
                                    @foreach ($item->services as $service)
                                        <li class="flex items-start gap-2.5 text-[0.9375rem] font-medium text-brand-navy">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            @if ($linkServices)
                                                <a href="{{ $service->url() }}" class="transition hover:text-brand-accent">{{ $service->title }}</a>
                                            @else
                                                {{ $service->title }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (filled($item->technologies))
                            <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-xs shadow-brand-navy/5">
                                <h2 class="text-xs font-semibold tracking-[0.15em] text-brand-navy/60 uppercase">Technologies</h2>
                                <ul class="mt-4 flex flex-wrap gap-2" aria-label="Technologies">
                                    @foreach ($item->technologies as $technology)
                                        <li class="rounded-full bg-brand-sky px-3 py-1 text-sm font-medium text-brand-navy/80">{{ $technology }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </aside>
                </div>
            </section>
        @endif

        {{-- Project gallery (only when images were added in the CMS) --}}
        @if ($gallery->isNotEmpty())
            <section class="bg-brand-mist py-16 sm:py-20" aria-labelledby="gallery-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <p class="text-xs font-semibold tracking-[0.18em] text-brand-accent uppercase">Gallery</p>
                    <h2 id="gallery-heading" class="mt-2 text-2xl font-bold tracking-tight text-brand-navy sm:text-3xl">Project gallery</h2>
                    <ul @class(['mt-8 grid gap-6 sm:grid-cols-2', 'lg:grid-cols-3' => $gallery->count() >= 3])>
                        @foreach ($gallery as $image)
                            <li>
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) }}"
                                    alt="{{ $image->alt_text ?: $item->title.' — image '.$loop->iteration }}"
                                    loading="lazy"
                                    decoding="async"
                                    class="aspect-[16/10] w-full rounded-2xl border border-brand-line bg-white object-cover shadow-xs shadow-brand-navy/5"
                                >
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- Project CTA (existing contact infrastructure) --}}
        <section @class(['py-14 sm:py-16', 'bg-white' => $gallery->isNotEmpty(), 'bg-brand-mist' => $gallery->isEmpty()])>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <x-portfolio.cta heading="Have a project like this in mind?" :class="$gallery->isNotEmpty() ? 'border border-brand-line' : ''"/>
            </div>
        </section>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
