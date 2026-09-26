{{--
    Hero "Page header band" style — the full-width dark header for inner
    CMS pages such as About: breadcrumbs, eyebrow, heading (+ optional
    accent line), subheading, up to two buttons, headline stats and an
    optional image. As the page's first section its heading is the page
    <h1> (the page view then skips its own title header — see
    resources/views/pages/show.blade.php).
--}}
@props(['section', 'content', 'first' => false])

@php
    $image = !empty($content['media_id']) ? \App\Models\Media::find($content['media_id']) : null;
    $imageUrl = $image ? \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) : null;
    $heading = $first ? 'h1' : 'h2';
    // The CMS page being viewed (route-model bound on pages.show), for the breadcrumb.
    $page = request()->route('page');
    $page = $page instanceof \App\Models\Page ? $page : null;
@endphp

<section
    @if (!empty($content['anchor'])) id="{{ $content['anchor'] }}" @endif
    class="relative isolate scroll-mt-24 overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark text-white {{ $first ? 'pt-28 pb-20 sm:pt-36 sm:pb-24' : 'py-16 sm:py-20' }}"
>
    <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-[28rem] w-[28rem] rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-24 -z-10 h-80 w-80 rounded-full bg-brand-accent-light/20 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        @if ($first && $page)
            <x-blog.breadcrumbs :items="[['label' => $page->title, 'url' => url()->current()]]" :dark="true" class="mb-8"/>
        @endif

        <div @class(['grid items-center gap-12', 'lg:grid-cols-12' => $imageUrl])>
            <div @class(['lg:col-span-7' => $imageUrl, 'max-w-3xl' => ! $imageUrl])>
                @if (!empty($content['eyebrow']))
                    <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-white/85 uppercase backdrop-blur">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        {{ $content['eyebrow'] }}
                    </p>
                @endif
                @if (!empty($content['heading']))
                    <{{ $heading }} class="mt-5 text-4xl leading-tight font-bold tracking-tight whitespace-pre-line sm:text-5xl">{{ $content['heading'] }}@if (!empty($content['heading_highlight']))<span class="block text-brand-accent-light">{{ $content['heading_highlight'] }}</span>@endif</{{ $heading }}>
                @endif
                @if (!empty($content['subheading']))
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/75">{{ $content['subheading'] }}</p>
                @endif

                @if ((!empty($content['cta_label']) && !empty($content['cta_url'])) || (!empty($content['secondary_cta_label']) && !empty($content['secondary_cta_url'])))
                    <div class="mt-8 flex flex-wrap gap-3">
                        @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                            <x-site.button :href="$content['cta_url']" size="lg" variant="light" class="shadow-lg">
                                {{ $content['cta_label'] }} <x-site.arrow class="h-4 w-4"/>
                            </x-site.button>
                        @endif
                        @if (!empty($content['secondary_cta_label']) && !empty($content['secondary_cta_url']))
                            <x-site.button :href="$content['secondary_cta_url']" variant="outline-light" size="lg">{{ $content['secondary_cta_label'] }}</x-site.button>
                        @endif
                    </div>
                @endif

                @if (!empty($content['stats']))
                    <dl class="mt-10 grid max-w-xl grid-cols-3 gap-4 border-t border-white/10 pt-8">
                        @foreach ($content['stats'] as $stat)
                            <div class="flex flex-col">
                                <dt class="order-2 mt-1 text-xs text-white/60 sm:text-sm">{{ $stat['label'] ?? '' }}</dt>
                                <dd class="order-1 text-2xl font-bold sm:text-3xl">{{ $stat['value'] ?? '' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            @if ($imageUrl)
                <div class="lg:col-span-5">
                    <div class="relative">
                        <div class="absolute -inset-3 -z-10 rounded-[2rem] bg-gradient-to-br from-brand-accent-light/40 to-transparent blur-sm" aria-hidden="true"></div>
                        <img src="{{ $imageUrl }}" alt="{{ $image->alt_text ?: '' }}" class="aspect-[4/3] w-full rounded-3xl object-cover shadow-2xl shadow-black/30" fetchpriority="high">
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
