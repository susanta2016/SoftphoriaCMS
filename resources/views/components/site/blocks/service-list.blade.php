{{--
    The Services section type: published services, each linking to its own
    page. Which services: the ones picked in content_json.service_slugs (in
    that order), or else those marked "Show on homepage", up to
    content_json.limit (default 6). Styles:

    - grid (default): the homepage's "Our Services" card grid;
    - spotlight: a dark band of large highlight cards with each service's
      tagline and key deliverables — e.g. "Specialised Expertise".

    Hidden while Features Activation has Services Pages off.
--}}
@props(['section', 'content', 'first' => false])

@php
    $slugs = array_values(array_filter((array) ($content['service_slugs'] ?? [])));
    $spotlight = ($content['style'] ?? 'grid') === 'spotlight';

    $services = collect();
    if (app(\App\Shared\Support\Features\Features::class)->enabled('services')) {
        $services = $slugs !== []
            ? \App\Models\Service::query()->published()->whereIn('slug', $slugs)->get()->sortBy(fn ($service) => array_search($service->slug, $slugs, true))->values()
            : \App\Models\Service::query()->published()->where('is_featured', true)->ordered()
                ->limit(max(1, min(12, (int) ($content['limit'] ?? 6))))->get();
    }
@endphp

@if ($services->isNotEmpty() && $spotlight)
    <section
        @if (!empty($content['anchor'])) id="{{ $content['anchor'] }}" @endif
        class="relative isolate scroll-mt-24 overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark py-16 text-white sm:py-20 lg:py-24"
    >
        <div class="pointer-events-none absolute -top-32 left-1/3 -z-10 h-96 w-96 rounded-full bg-brand-accent/35 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.07)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-site.section-heading
                :eyebrow="$content['eyebrow'] ?? null"
                :heading="$content['heading'] ?? null"
                :description="$content['description'] ?? null"
                :link-label="$content['link_label'] ?? null"
                :link-url="($content['link_url'] ?? null) ?: route('services.index')"
                :dark="true"
            />

            <div @class([
                'mt-10 grid gap-6',
                'md:grid-cols-2' => $services->count() === 2 || $services->count() >= 4,
                'md:grid-cols-3' => $services->count() === 3,
                'lg:grid-cols-4' => $services->count() >= 4,
            ])>
                @foreach ($services as $service)
                    <article class="group relative flex flex-col rounded-3xl border border-white/10 bg-white/[0.06] p-7 backdrop-blur-sm transition duration-300 hover:-translate-y-1 hover:border-white/25 hover:bg-white/[0.1]">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-accent-light to-brand-accent text-white shadow-lg shadow-brand-accent/30" aria-hidden="true">
                            <x-site.icon :name="$service->icon ?: 'monitor'" :mono="true" class="h-7 w-7"/>
                        </span>
                        <h3 class="mt-6 text-xl font-bold">
                            <a href="{{ $service->url() }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:after:rounded-3xl focus-visible:after:ring-2 focus-visible:after:ring-white">{{ $service->title }}</a>
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-white/70">{{ $service->tagline ?: $service->summary }}</p>

                        @if (filled($service->highlights))
                            <ul class="mt-6 space-y-2.5 border-t border-white/10 pt-6">
                                @foreach (array_slice($service->highlights, 0, 4) as $highlight)
                                    <li class="flex items-start gap-2.5 text-sm text-white/85">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="mt-0.5 h-4 w-4 shrink-0 text-brand-accent-light" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        {{ $highlight['title'] ?? '' }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <span class="mt-auto inline-flex items-center gap-1.5 pt-7 text-sm font-semibold text-brand-accent-light transition group-hover:text-white" aria-hidden="true">
                            {{ ($content['item_link_label'] ?? null) ?: 'Explore service' }}
                            <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-1"/>
                        </span>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@elseif ($services->isNotEmpty())
    <x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
        <x-site.section-heading
            :eyebrow="$content['eyebrow'] ?? null"
            :heading="$content['heading'] ?? null"
            :description="$content['description'] ?? null"
            :link-label="$content['link_label'] ?? null"
            :link-url="($content['link_url'] ?? null) ?: route('services.index')"
        />

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $service)
                <x-service.card :service="$service" :link-label="($content['item_link_label'] ?? null) ?: 'Learn more'"/>
            @endforeach
        </div>
    </x-site.band>
@endif
