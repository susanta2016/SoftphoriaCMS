{{--
    /services/{slug} — one service: header, overview with a sticky sidebar
    (tech stack + call to action + other services), what's included, FAQs
    (FAQPage structured data is built in ServiceController), related blog
    posts, other services and the call-to-action band.
--}}
<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
        @if ($isPreview)
            <div class="fixed inset-x-0 bottom-0 z-40 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950">
                Preview — this service is unpublished and not visible to the public.
            </div>
        @endif

        {{-- Header --}}
        <header class="relative isolate overflow-hidden bg-gradient-to-b from-brand-sky to-white pt-28 pb-14 sm:pt-36">
            <div class="pointer-events-none absolute -top-32 right-0 -z-10 h-96 w-[40rem] rounded-full bg-brand-accent/10 blur-3xl" aria-hidden="true"></div>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <x-blog.breadcrumbs :items="[['label' => 'Services', 'url' => route('services.index')], ['label' => $service->title, 'url' => $service->url()]]"/>

                <div class="mt-8 flex flex-col gap-6 sm:flex-row sm:items-start">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-brand-accent text-white shadow-lg shadow-brand-accent/25" aria-hidden="true">
                        <x-site.icon :name="$service->icon ?: 'monitor'" :mono="true" class="h-8 w-8"/>
                    </span>
                    <div class="max-w-3xl">
                        <h1 class="text-4xl leading-tight font-bold tracking-tight text-brand-navy sm:text-5xl">{{ $service->title }}</h1>
                        @if ($service->tagline)
                            <p class="mt-4 text-xl font-medium text-brand-navy/80">{{ $service->tagline }}</p>
                        @endif
                        @if ($service->summary)
                            <p class="mt-3 text-lg leading-relaxed text-brand-navy/65">{{ $service->summary }}</p>
                        @endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            @if ($settings->get('cta_label') && $settings->get('cta_url'))
                                <a href="{{ $settings->get('cta_url') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-accent px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-accent/25 transition hover:bg-brand-accent-dark">
                                    {{ $settings->get('cta_label') }} <x-site.arrow class="h-4 w-4"/>
                                </a>
                            @endif
                            <a href="{{ route('services.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-brand-navy/15 bg-white px-6 py-3.5 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent">All services</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        @if ($coverUrl)
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <img src="{{ $coverUrl }}" alt="{{ $service->cover->alt_text ?: $service->title }}" fetchpriority="high" class="aspect-[21/9] w-full rounded-3xl object-cover shadow-2xl shadow-brand-navy/10">
            </div>
        @endif

        {{-- Overview + sidebar --}}
        <div class="mx-auto mt-14 grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-12">
            <div class="min-w-0 lg:col-span-8">
                @if (filled($bodyHtml))
                    <div class="blog-prose">{!! $bodyHtml !!}</div>
                @endif
            </div>

            <aside class="lg:col-span-4" aria-label="{{ $service->title }} at a glance">
                <div class="space-y-6 lg:sticky lg:top-28">
                    @if (filled($service->technologies))
                        <div class="rounded-2xl border border-brand-navy/10 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Tech stack</p>
                            <ul class="mt-4 flex flex-wrap gap-2">
                                @foreach ($service->technologies as $technology)
                                    <li class="rounded-full bg-brand-sky px-3 py-1 text-sm font-medium text-brand-navy/80">{{ $technology }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($settings->get('cta_label') && $settings->get('cta_url'))
                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-accent to-brand-accent-dark p-6 text-white shadow-xl shadow-brand-accent/20">
                            <div class="pointer-events-none absolute -right-10 -bottom-12 h-40 w-40 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                            <p class="text-lg font-bold">Need help with {{ $service->title }}?</p>
                            <p class="mt-2 text-sm leading-relaxed text-white/85">Tell us about your project and get practical next steps from our team.</p>
                            <a href="{{ $settings->get('cta_url') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-brand-navy transition hover:bg-brand-sky">
                                {{ $settings->get('cta_label') }} <x-site.arrow class="h-4 w-4"/>
                            </a>
                        </div>
                    @endif

                    @if ($others->isNotEmpty())
                        <nav class="rounded-2xl border border-brand-navy/10 bg-white p-6 shadow-sm" aria-label="Other services">
                            <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Other services</p>
                            <ul class="mt-3 space-y-1">
                                @foreach ($others as $other)
                                    <li>
                                        <a href="{{ $other->url() }}" class="group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-medium text-brand-navy/80 transition hover:bg-brand-mist hover:text-brand-accent">
                                            <x-site.icon :name="$other->icon ?: 'monitor'" class="h-5 w-5 shrink-0 text-brand-accent"/>
                                            <span class="flex-1">{{ $other->title }}</span>
                                            <x-site.arrow class="h-3.5 w-3.5 opacity-0 transition group-hover:opacity-100"/>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                </div>
            </aside>
        </div>

        {{-- What's included --}}
        @if (filled($service->highlights))
            <section class="mt-20 bg-brand-mist py-16 sm:py-20" aria-labelledby="included-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">What's included</p>
                    <h2 id="included-heading" class="mt-2 text-3xl font-bold tracking-tight text-brand-navy">What you get with {{ $service->title }}</h2>
                    <ul @class(['mt-10 grid gap-5 sm:grid-cols-2', 'lg:grid-cols-3' => count($service->highlights) % 3 === 0])>
                        @foreach ($service->highlights as $highlight)
                            <li class="flex gap-4 rounded-2xl border border-brand-navy/8 bg-white p-6 shadow-sm">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-sky text-brand-accent" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-5 w-5"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <div>
                                    <h3 class="font-bold text-brand-navy">{{ $highlight['title'] ?? '' }}</h3>
                                    @if (filled($highlight['description'] ?? null))
                                        <p class="mt-1.5 text-sm leading-relaxed text-brand-navy/70">{{ $highlight['description'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @else
            <div class="h-20"></div>
        @endif

        {{-- FAQs --}}
        @if (filled($service->faqs))
            <section class="bg-white py-16 sm:py-20" aria-labelledby="faq-heading">
                <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">FAQ</p>
                        <h2 id="faq-heading" class="mt-2 text-3xl font-bold tracking-tight text-brand-navy">Common questions</h2>
                        <p class="mt-4 text-brand-navy/65">Can't find what you're looking for? <a href="{{ route('contact.index') }}" class="font-semibold text-brand-accent hover:text-brand-accent-dark">Ask us directly</a>.</p>
                    </div>
                    <div class="space-y-3 lg:col-span-8">
                        @foreach ($service->faqs as $faq)
                            <details class="group rounded-2xl border border-brand-navy/10 bg-white px-6 py-5 shadow-sm open:border-brand-accent/30 open:shadow-md" @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-brand-navy [&::-webkit-details-marker]:hidden">
                                    {{ $faq['question'] ?? '' }}
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-sky text-brand-accent transition group-open:rotate-45" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-4 w-4"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                                    </span>
                                </summary>
                                <p class="mt-3 leading-relaxed text-brand-navy/70">{{ $faq['answer'] ?? '' }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Related reading --}}
        @if ($relatedPosts->isNotEmpty())
            @php $blogSettings = app(\App\Shared\Support\Blog\BlogSettingsRepository::class); @endphp
            <section class="border-t border-brand-navy/5 bg-white py-16" aria-labelledby="reading-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="reading-heading" class="text-2xl font-bold text-brand-navy">Related reading</h2>
                        <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-accent hover:text-brand-accent-dark">All articles <x-site.arrow class="h-3.5 w-3.5"/></a>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($relatedPosts as $post)
                            <x-blog.card :post="$post" :style="$blogSettings->get('card_style')" :show-author="$blogSettings->get('show_author')" :show-reading-time="$blogSettings->get('show_reading_time')" :show-category="app(\App\Shared\Support\Features\Features::class)->enabled('blog.categories')"/>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Other services --}}
        @if ($others->isNotEmpty())
            <section class="bg-brand-mist py-16 sm:py-20" aria-labelledby="others-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="others-heading" class="text-2xl font-bold text-brand-navy">Explore other services</h2>
                        <a href="{{ route('services.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-accent hover:text-brand-accent-dark">All services <x-site.arrow class="h-3.5 w-3.5"/></a>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($others as $other)
                            <x-service.card :service="$other"/>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <x-service.cta :settings="$settings"/>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
