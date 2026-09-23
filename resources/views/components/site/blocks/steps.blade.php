{{--
    WEB-103 — Gallery "Numbered steps", restyled for the redesign: a row of
    cards with a round icon badge, the step number and an arrow between
    cards from lg up (stacked, arrow-less, on smaller screens).
--}}
@props(['section', 'content', 'media'])

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <x-site.section-heading
        :eyebrow="$content['eyebrow'] ?? null"
        :heading="($content['heading'] ?? null) ?: $section->title"
        :description="$content['description'] ?? null"
        :link-label="$content['link_label'] ?? null"
        :link-url="$content['link_url'] ?? null"
    />

    <ol class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-10">
        @foreach ($content['gallery_items'] ?? [] as $item)
            <li class="relative flex gap-4 rounded-xl border border-brand-navy/10 bg-white p-5 shadow-sm">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-accent text-white">
                    <x-site.icon :name="$item['icon'] ?? null" class="h-5 w-5"/>
                </span>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-brand-accent">{{ sprintf('%02d', $loop->iteration) }}</span>
                    @if (!empty($item['title']))
                        <h3 class="font-semibold text-brand-navy">{{ $item['title'] }}</h3>
                    @endif
                    @if (!empty($item['description']))
                        <p class="mt-1 text-xs leading-relaxed text-brand-navy/65">{{ $item['description'] }}</p>
                    @endif
                </div>
                @unless ($loop->last)
                    <x-site.arrow class="absolute top-1/2 -right-8 hidden h-5 w-5 -translate-y-1/2 text-brand-navy/40 lg:block"/>
                @endunless
            </li>
        @endforeach
    </ol>
</x-site.band>
