{{--
    WEB-103 — Gallery "Numbered steps": a connected progression. From lg up
    the steps sit in a row, each round icon badge joined to the next by a
    thin blue line (01 ●──── 02 ●──── …); below lg they become a vertical
    sequence with the line running down between the badges. The connector
    is decorative (aria-hidden) — the ordered list carries the sequence.
--}}
@props(['section', 'content', 'media'])

@php $tint = ($content['background'] ?? 'white') === 'tint'; @endphp

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <x-site.section-heading
        :eyebrow="$content['eyebrow'] ?? null"
        :heading="($content['heading'] ?? null) ?: $section->title"
        :description="$content['description'] ?? null"
        :link-label="$content['link_label'] ?? null"
        :link-url="$content['link_url'] ?? null"
    />

    <ol class="mt-12 grid gap-y-0 lg:grid-cols-4 lg:gap-x-10">
        @foreach ($content['gallery_items'] ?? [] as $item)
            <li class="relative flex gap-5 pb-10 last:pb-0 lg:flex-col lg:gap-0 lg:pb-0">
                @unless ($loop->last)
                    <span class="absolute top-14 bottom-2 left-6 w-0.5 rounded-full bg-gradient-to-b from-brand-accent/60 to-brand-accent/15 lg:top-6 lg:right-[-1.75rem] lg:bottom-auto lg:left-[4.25rem] lg:h-0.5 lg:w-auto lg:bg-gradient-to-r" aria-hidden="true"></span>
                @endunless
                <span @class([
                    'relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-accent text-white shadow-md shadow-brand-accent/25 ring-4',
                    'ring-brand-mist' => $tint,
                    'ring-white' => ! $tint,
                ]) aria-hidden="true">
                    <x-site.icon :name="$item['icon'] ?? null" class="h-5 w-5"/>
                </span>
                <div class="min-w-0 pt-1 lg:mt-6 lg:pt-0">
                    <span class="text-sm font-bold tracking-wider text-brand-accent tabular-nums">{{ sprintf('%02d', $loop->iteration) }}</span>
                    @if (!empty($item['title']))
                        <h3 class="mt-1 text-lg font-semibold text-brand-navy">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mt-2 max-w-xs text-[0.9375rem] leading-relaxed text-brand-muted">{{ $item['description'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</x-site.band>
