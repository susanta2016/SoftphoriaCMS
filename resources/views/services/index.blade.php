{{--
    /services — the Services landing page: hero with quick links to every
    published service, the grid of primary services, the homepage's own
    "Why Softphoria" and process sections, and the Services Settings call
    to action (→ the existing Contact page).
--}}
<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
        {{-- Hero --}}
        <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark pt-28 pb-20 text-white sm:pt-36 sm:pb-24">
            <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-[28rem] w-[28rem] rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-24 -z-10 h-80 w-80 rounded-full bg-brand-accent-light/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <x-blog.breadcrumbs :items="[['label' => 'Services', 'url' => route('services.index')]]" :dark="true"/>

                <div class="mt-8 grid items-center gap-12 lg:grid-cols-12">
                    <div class="lg:col-span-7">
                        <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-white/85 uppercase backdrop-blur">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            What we do
                        </p>
                        <h1 class="mt-5 text-4xl leading-tight font-bold tracking-tight sm:text-5xl">{{ $settings->get('title') }}</h1>
                        @if ($settings->get('intro'))
                            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/75">{{ $settings->get('intro') }}</p>
                        @endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            @if ($settings->get('cta_label') && $settings->get('cta_url'))
                                <a href="{{ $settings->get('cta_url') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-brand-navy shadow-lg transition hover:bg-brand-sky">
                                    {{ $settings->get('cta_label') }} <x-site.arrow class="h-4 w-4"/>
                                </a>
                            @endif
                            <a href="#all-services" class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-6 py-3.5 text-sm font-semibold text-white transition hover:border-white hover:bg-white/10">Explore services</a>
                        </div>
                    </div>

                    @if ($services->isNotEmpty())
                        <nav aria-label="Our services" class="lg:col-span-5">
                            <ul class="grid gap-2 rounded-3xl border border-white/10 bg-white/5 p-3 backdrop-blur-md sm:grid-cols-2 lg:grid-cols-1">
                                @foreach ($services as $service)
                                    <li>
                                        <a href="{{ $service->url() }}" class="group flex items-center gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-white/10">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white" aria-hidden="true">
                                                <x-site.icon :name="$service->icon ?: 'monitor'" :mono="true" class="h-5 w-5"/>
                                            </span>
                                            <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $service->title }}</span>
                                            <x-site.arrow class="h-4 w-4 shrink-0 text-white/50 transition group-hover:translate-x-0.5 group-hover:text-white"/>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                </div>
            </div>
        </section>

        {{-- Service grid --}}
        <section id="all-services" class="scroll-mt-24 bg-brand-mist py-16 sm:py-20" aria-labelledby="all-services-heading">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <h2 id="all-services-heading" class="text-3xl font-bold tracking-tight text-brand-navy">Our services</h2>

                @if ($primaryServices->isEmpty())
                    <p class="mt-10 rounded-3xl border border-dashed border-brand-navy/15 bg-white px-6 py-16 text-center text-brand-navy/60">Our services are being updated — please check back soon.</p>
                @else
                    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($primaryServices as $service)
                            <x-service.card :service="$service" :detailed="true" heading-level="h3"/>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- The homepage's own "Why Softphoria" and "Our Process" blocks (Pages → Home), not second copies. --}}
        @if ($sharedSections->isNotEmpty())
            {{-- Narrowed to this page's max-w-6xl column so they line up with the grid above. --}}
            <div class="[&>section>div]:max-w-6xl lg:[&>section>div]:px-6">
                <x-site.sections :sections="$sharedSections"/>
            </div>
        @endif

        <x-service.cta :settings="$settings"/>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
