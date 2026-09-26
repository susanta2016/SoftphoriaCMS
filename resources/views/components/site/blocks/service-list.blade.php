{{--
    The Services section type (the homepage's "Our Services"): published
    services marked "Show on homepage" in Admin → Services, in sort order,
    up to content_json.limit (default 6), each linking to its own page.
    Hidden while Features Activation has Services Pages off.
--}}
@props(['section', 'content'])

@php
    $services = app(\App\Shared\Support\Features\Features::class)->enabled('services')
        ? \App\Models\Service::query()
            ->published()
            ->where('is_featured', true)
            ->ordered()
            ->limit(max(1, min(12, (int) ($content['limit'] ?? 6))))
            ->get()
        : collect();
@endphp

@if ($services->isNotEmpty())
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
