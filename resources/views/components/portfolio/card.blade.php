{{--
    One portfolio project card — the homepage Featured Portfolio section and
    the /portfolio page. The whole card links to the project's detail page
    (/portfolio/{slug}) through one stretched title link; the optional
    external project URL lives on that page. With no cover image, a branded
    placeholder (the item's icon on a navy-to-blue gradient) keeps the card
    shape — never a fake screenshot. `aspect` sets the image ratio (the
    homepage uses a wider one when only one or two projects are featured).
    Services show only when the caller eager-loaded them (published only).
--}}
@props(['item', 'linkLabel' => null, 'headingLevel' => 'h3', 'aspect' => 'aspect-[16/10]'])

@php $services = $item->relationLoaded('services') ? $item->services : collect(); @endphp

<article class="group relative flex flex-col overflow-hidden rounded-xl border border-brand-line bg-white shadow-xs shadow-brand-navy/5 transition duration-300 hover:-translate-y-0.5 hover:border-brand-accent/35 hover:shadow-md hover:shadow-brand-navy/8">
    <div class="overflow-hidden">
        @if ($item->cover)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->cover->disk)->url($item->cover->path) }}" alt="{{ $item->cover->alt_text ?: $item->title }}" class="{{ $aspect }} w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" decoding="async">
        @else
            <div class="flex {{ $aspect }} w-full items-center justify-center bg-gradient-to-br from-brand-navy via-brand-navy-mid to-brand-royal text-white/85" aria-hidden="true">
                <x-site.icon :name="$item->icon ?: 'monitor'" :mono="true" class="h-14 w-14"/>
            </div>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-6 sm:p-7">
        @if ($item->category)
            <p class="text-xs font-semibold tracking-[0.12em] text-brand-accent uppercase">{{ $item->category }}</p>
        @endif
        <{{ $headingLevel }} @class(['text-lg font-bold text-brand-navy transition group-hover:text-brand-accent sm:text-xl', 'mt-2' => $item->category])>
            <a href="{{ $item->url() }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:after:rounded-xl focus-visible:after:ring-2 focus-visible:after:ring-brand-accent">{{ $item->title }}</a>
        </{{ $headingLevel }}>
        @if ($item->summary)
            <p class="mt-2 text-[0.9375rem] leading-relaxed text-brand-muted">{{ $item->summary }}</p>
        @endif
        @if ($services->isNotEmpty())
            <p class="mt-3 text-sm text-brand-navy/70"><span class="font-semibold text-brand-navy/80">Services:</span> {{ $services->pluck('title')->implode(' · ') }}</p>
        @endif
        @if (! empty($item->technologies))
            <ul class="mt-4 flex flex-wrap gap-1.5" aria-label="Technologies">
                @foreach ($item->technologies as $technology)
                    <li class="rounded-full bg-brand-sky px-2.5 py-0.5 text-xs font-medium text-brand-navy/75">{{ $technology }}</li>
                @endforeach
            </ul>
        @endif
        @if ($linkLabel)
            <span class="mt-auto inline-flex items-center gap-1.5 self-start pt-5 text-sm font-semibold text-brand-accent" aria-hidden="true">
                {{ $linkLabel }}
                <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"/>
            </span>
        @endif
    </div>
</article>
