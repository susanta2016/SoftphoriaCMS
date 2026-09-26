{{--
    One tool card: the /tools hub grid and "Related tools" on a tool page.
    The whole card is clickable through a stretched title link. The data
    attributes feed the hub's instant search and category filter.
--}}
@props(['tool', 'headingLevel' => 'h3'])

<article {{ $attributes->class(['group relative flex h-full flex-col rounded-2xl border border-brand-navy/8 bg-white p-6 shadow-sm shadow-brand-navy/5 transition duration-300 hover:-translate-y-1 hover:border-brand-accent/30 hover:shadow-xl hover:shadow-brand-navy/10']) }}
         data-tool-card
         data-category="{{ $tool->category?->slug }}"
         data-search="{{ mb_strtolower(implode(' ', array_filter([$tool->name, $tool->short_description, $tool->category?->name]))) }}">
    <div class="flex items-start justify-between gap-4">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sky text-brand-accent transition duration-300 group-hover:bg-brand-accent group-hover:text-white" aria-hidden="true">
            <x-site.icon :name="$tool->icon ?: 'code'" class="h-6 w-6"/>
        </span>
        @if ($tool->category)
            <span class="rounded-full bg-brand-mist px-3 py-1 text-xs font-semibold text-brand-navy/70">{{ $tool->category->name }}</span>
        @endif
    </div>

    <{{ $headingLevel }} class="mt-5 text-lg font-bold text-brand-navy transition group-hover:text-brand-accent">
        <a href="{{ $tool->url() }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:after:rounded-2xl focus-visible:after:ring-2 focus-visible:after:ring-brand-accent">{{ $tool->name }}</a>
    </{{ $headingLevel }}>

    @if ($tool->short_description)
        <p class="mt-2 text-sm leading-relaxed text-brand-navy/70">{{ $tool->short_description }}</p>
    @endif

    <span class="mt-auto inline-flex items-center gap-1.5 pt-6 text-sm font-semibold text-brand-accent" aria-hidden="true">
        Open tool
        <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-1"/>
    </span>
</article>
