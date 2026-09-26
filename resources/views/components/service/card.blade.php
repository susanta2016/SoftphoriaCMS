{{--
    One service card — the /services landing grid, "Other services" on a
    service page, and the homepage Services section. The whole card is
    clickable through a stretched title link (one link per card).
    `detailed` adds the first few "What's included" items as a checklist.
--}}
@props(['service', 'detailed' => false, 'linkLabel' => 'Explore service', 'headingLevel' => 'h3'])

<article class="group relative flex h-full flex-col rounded-2xl border border-brand-navy/8 bg-white p-6 shadow-sm shadow-brand-navy/5 transition duration-300 hover:-translate-y-1 hover:border-brand-accent/30 hover:shadow-xl hover:shadow-brand-navy/10 sm:p-7">
    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-sky text-brand-accent transition duration-300 group-hover:bg-brand-accent group-hover:text-white" aria-hidden="true">
        <x-site.icon :name="$service->icon ?: 'monitor'" class="h-7 w-7"/>
    </span>

    <{{ $headingLevel }} class="mt-5 text-lg font-bold text-brand-navy transition group-hover:text-brand-accent">
        <a href="{{ $service->url() }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:after:rounded-2xl focus-visible:after:ring-2 focus-visible:after:ring-brand-accent">{{ $service->title }}</a>
    </{{ $headingLevel }}>

    @if ($service->summary)
        <p class="mt-2 text-sm leading-relaxed text-brand-navy/70">{{ $service->summary }}</p>
    @endif

    @if ($detailed && filled($service->highlights))
        <ul class="mt-5 space-y-2 border-t border-brand-navy/8 pt-5">
            @foreach (array_slice($service->highlights, 0, 3) as $highlight)
                <li class="flex items-start gap-2.5 text-sm text-brand-navy/80">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ $highlight['title'] ?? '' }}
                </li>
            @endforeach
        </ul>
    @endif

    <span class="mt-auto inline-flex items-center gap-1.5 pt-6 text-sm font-semibold text-brand-accent" aria-hidden="true">
        {{ $linkLabel }}
        <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-1"/>
    </span>
</article>
