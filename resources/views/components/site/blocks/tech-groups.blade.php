{{--
    WEB-103 — Gallery "Grouped logos": e.g. the homepage's "Technologies we
    work with" (Backend / Frontend / Cloud & DevOps / Databases). Items are
    grouped by their `group` field, in order of first appearance.
--}}
@props(['section', 'content', 'media'])

@php
    $groups = collect($content['gallery_items'] ?? [])->groupBy(fn (array $item): string => trim($item['group'] ?? '') ?: 'Other');
@endphp

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    <x-site.section-heading
        :eyebrow="$content['eyebrow'] ?? null"
        :heading="$content['heading'] ?? null"
        :description="$content['description'] ?? null"
        :link-label="$content['link_label'] ?? null"
        :link-url="$content['link_url'] ?? null"
    />

    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($groups as $group => $items)
            <div class="rounded-xl border border-brand-line bg-white p-5 shadow-xs shadow-brand-navy/5 sm:p-6">
                <h3 class="border-b border-brand-line pb-3 text-base font-semibold text-brand-navy">{{ $group }}</h3>
                {{-- Fixed 2 / 3 columns below lg so labels like WooCommerce never collide and logos stay large on phones; from lg up, auto-fill with a minimum cell width as before. --}}
                <ul class="mt-5 grid grid-cols-2 gap-x-3 gap-y-5 min-[360px]:grid-cols-3 lg:grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] lg:gap-x-2">
                    @foreach ($items as $item)
                        @php $itemMedia = $media->get($item['media_id'] ?? null); @endphp
                        <li class="flex min-w-0 flex-col items-center gap-2 text-center">
                            @if ($itemMedia)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($itemMedia->disk)->url($itemMedia->path) }}" alt="" class="h-10 w-10 object-contain sm:h-9 sm:w-9" loading="lazy">
                            @else
                                <x-site.icon :name="$item['icon'] ?? null" class="h-10 w-10 sm:h-9 sm:w-9"/>
                            @endif
                            <span class="text-[0.8125rem] leading-tight font-medium break-words text-brand-ink/75 lg:text-xs">{{ $item['title'] ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-site.band>
