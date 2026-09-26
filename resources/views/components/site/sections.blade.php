{{--
    WEB-102 — the generic "render a list of PageSection rows" partial,
    extracted from resources/views/pages/show.blade.php so the same section
    rendering (rich_text/image_text/faq/quote/cta/gallery/contact_form, plus
    a simple generic hero) is shared by both the generic CMS page view and
    the Homepage (resources/views/home.blade.php) instead of two copies.
    Home renders its own bespoke full-bleed hero separately and only passes
    its non-hero sections in here — see HomeController.

    Every currently-supported section type keeps its exact WEB-101 public
    markup/behavior. The only addition is the Gallery section's optional
    content_json.display ('grid' | 'steps' | 'quotes'), used to present the
    same title/description/url/media item shape as a numbered process or a
    set of quote cards instead of a photo/portfolio grid — see PageForm's
    Gallery "Display as" field. Existing galleries have no `display` key and
    keep rendering as 'grid', unchanged.
--}}
@props(['sections'])

@forelse ($sections as $section)
    @php
        $content = $section->content_json ?? [];

        // WEB-103: the redesigned homepage's full-width blocks render
        // through their own components (resources/views/components/site/blocks/*)
        // inside an edge-to-edge x-site.band rather than the narrow
        // x-site.section below. Everything else keeps its existing markup.
        $block = match (true) {
            $section->section_type === \App\Enums\PageSectionType::Testimonials->value => 'testimonials',
            $section->section_type === \App\Enums\PageSectionType::Portfolio->value => 'portfolio',
            $section->section_type === \App\Enums\PageSectionType::BlogPosts->value => 'blog-posts',
            $section->section_type === \App\Enums\PageSectionType::Services->value => 'service-list',
            $section->section_type === \App\Enums\PageSectionType::Cta->value && ($content['style'] ?? null) === 'banner' => 'cta-banner',
            $section->section_type === \App\Enums\PageSectionType::Gallery->value => match ($content['display'] ?? 'grid') {
                'logos' => 'logos',
                'services' => 'services',
                'features' => 'features',
                'projects' => 'projects',
                'tech_groups' => 'tech-groups',
                'steps' => 'steps',
                'articles' => 'articles',
                default => null,
            },
            default => null,
        };
    @endphp

    @if (in_array($block, ['testimonials', 'cta-banner', 'portfolio', 'blog-posts', 'service-list'], true))
        <x-dynamic-component :component="'site.blocks.'.$block" :section="$section" :content="$content"/>
        @continue
    @elseif ($block)
        @php
            $blockMedia = \App\Models\Media::query()
                ->whereIn('id', collect($content['gallery_items'] ?? [])->pluck('media_id')->filter()->all())
                ->get()
                ->keyBy('id');
        @endphp
        <x-dynamic-component :component="'site.blocks.'.$block" :section="$section" :content="$content" :media="$blockMedia"/>
        @continue
    @endif

    <x-site.section>
        @switch($section->section_type)
            @case('hero')
                <div class="text-center">
                    @if (!empty($content['media_id']) && ($media = \App\Models\Media::find($content['media_id'])))
                        <img
                            class="mx-auto mb-4 max-w-full rounded-lg"
                            src="{{ \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) }}"
                            alt="{{ $media->alt_text ?? '' }}"
                        >
                    @endif
                    @if (!empty($content['eyebrow']))
                        <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">{{ $content['eyebrow'] }}</p>
                    @endif
                    @if (!empty($content['heading']))
                        <h2 class="text-2xl font-bold text-brand-navy">{{ $content['heading'] }}</h2>
                    @endif
                    @if (!empty($content['subheading']))
                        <p class="mt-2 text-brand-navy/70">{{ $content['subheading'] }}</p>
                    @endif
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                        @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                            <x-site.button :href="$content['cta_url']">{{ $content['cta_label'] }}</x-site.button>
                        @endif
                        @if (!empty($content['secondary_cta_label']) && !empty($content['secondary_cta_url']))
                            <x-site.button :href="$content['secondary_cta_url']" variant="outline">{{ $content['secondary_cta_label'] }}</x-site.button>
                        @endif
                    </div>
                    @if (!empty($content['tertiary_label']) && !empty($content['tertiary_url']))
                        <p class="mt-3">
                            <a href="{{ $content['tertiary_url'] }}" class="text-sm font-medium text-brand-navy underline decoration-brand-navy/30 underline-offset-2 hover:text-brand-accent">{{ $content['tertiary_label'] }}</a>
                        </p>
                    @endif
                </div>
                @break

            @case('rich_text')
                @if ($section->title)
                    <h2 class="text-2xl font-bold text-brand-navy">{{ $section->title }}</h2>
                @endif
                <div @class(['max-w-none text-brand-navy [&_a]:text-brand-accent [&_a]:underline [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-brand-navy [&_p]:mt-3 [&_ul]:mt-4 [&_ul]:flex [&_ul]:flex-wrap [&_ul]:list-none [&_ul]:gap-2 [&_ul]:p-0 [&_li]:rounded-full [&_li]:border [&_li]:border-brand-navy/15 [&_li]:bg-brand-sky/40 [&_li]:px-3.5 [&_li]:py-1.5 [&_li]:text-sm [&_li]:text-brand-navy [&_h3]:mt-4 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-brand-navy', 'mt-3' => (bool) $section->title])>
                    {!! $content['body'] ?? '' !!}
                </div>
                @break

            @case('image_text')
                <div class="flex flex-wrap items-start gap-6">
                    @if (!empty($content['media_id']) && ($media = \App\Models\Media::find($content['media_id'])))
                        <img
                            class="max-w-full flex-1 basis-72 rounded-lg"
                            src="{{ \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) }}"
                            alt="{{ $media->alt_text ?? '' }}"
                        >
                    @endif
                    <div class="flex-2 basis-80 text-brand-navy/80">{{ $content['text'] ?? '' }}</div>
                </div>
                @break

            @case('faq')
                <dl class="divide-y divide-brand-navy/10">
                    @foreach ($content['items'] ?? [] as $item)
                        <div class="py-4 first:pt-0">
                            <dt class="font-semibold text-brand-navy">{{ $item['question'] ?? '' }}</dt>
                            <dd class="mt-1 text-brand-navy/70">{{ $item['answer'] ?? '' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @break

            @case('quote')
                <blockquote class="border-l-4 border-brand-accent pl-4 text-xl text-brand-navy italic">
                    {{ $content['quote'] ?? '' }}
                    @if (!empty($content['attribution']))
                        <span class="mt-2 block text-sm font-normal text-brand-navy/60 not-italic">{{ $content['attribution'] }}</span>
                    @endif
                </blockquote>
                @break

            @case('cta')
                <div class="text-center">
                    @if (!empty($content['eyebrow']))
                        <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">{{ $content['eyebrow'] }}</p>
                    @endif
                    @if (!empty($content['heading']))
                        <h2 class="mt-1 text-2xl font-bold text-brand-navy">{{ $content['heading'] }}</h2>
                    @endif
                    @if (!empty($content['description']))
                        <p class="mx-auto mt-3 max-w-2xl text-brand-navy/70">{{ $content['description'] }}</p>
                    @endif
                    @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                        <div class="mt-5">
                            <x-site.button :href="$content['cta_url']">{{ $content['cta_label'] }}</x-site.button>
                        </div>
                    @endif
                </div>
                @break

            @case('gallery')
                @if ($section->title)
                    <h2 class="mb-6 text-center text-2xl font-bold text-brand-navy">{{ $section->title }}</h2>
                @endif

                @php $display = $content['display'] ?? 'grid'; @endphp

                {{-- 'steps' and WEB-103's other full-width displays are dispatched to site.blocks.* above. --}}
                @if ($display === 'quotes' && !empty($content['gallery_items']))
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($content['gallery_items'] as $item)
                            <x-site.card>
                                @if (!empty($item['description']))
                                    <blockquote class="text-brand-navy/85 italic">&ldquo;{{ $item['description'] }}&rdquo;</blockquote>
                                @endif
                                @if (!empty($item['title']))
                                    <p class="mt-3 text-sm font-semibold text-brand-navy not-italic">{{ $item['title'] }}</p>
                                @endif
                            </x-site.card>
                        @endforeach
                    </div>
                @elseif (!empty($content['gallery_items']))
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        @foreach ($content['gallery_items'] as $item)
                            @continue(empty($item['media_id']) && empty($item['title']))
                            <x-site.portfolio-item
                                :media="!empty($item['media_id']) ? \App\Models\Media::find($item['media_id']) : null"
                                :title="$item['title'] ?? null"
                                :description="$item['description'] ?? null"
                                :url="$item['url'] ?? null"
                                :icon="$item['icon'] ?? null"
                            />
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        @foreach (\App\Models\Media::query()->whereIn('id', $content['media_ids'] ?? [])->get() as $media)
                            <x-site.portfolio-item :media="$media"/>
                        @endforeach
                    </div>
                @endif
                @break

            @case('contact_form')
                {{--
                    WEB-102: also shows the site's real contact details
                    (Website Setup → Contact settings, WEB-101 item F) above
                    the form, matching the dedicated Contact page's own
                    layout — reads Settings directly so every place a
                    Contact Form section appears stays in sync automatically.
                --}}
                @php $contactSettings = app(\App\Shared\Services\Settings\SettingsRepository::class); @endphp
                <x-site.contact-info
                    class="mb-6"
                    :email="$contactSettings->get('contact', 'email')"
                    :phone="$contactSettings->get('contact', 'phone')"
                    :whatsapp="$contactSettings->get('contact', 'whatsapp')"
                    :address="$contactSettings->get('contact', 'address')"
                />
                <x-site.contact-form :heading="$section->title ?: null"/>
                @break

            @default
                <p class="rounded-md border border-dashed border-brand-navy/20 p-4 text-sm text-brand-navy/50">{{ $section->title ?: \App\Enums\PageSectionType::from($section->section_type)->getLabel() }} — no rendering yet for this block type.</p>
        @endswitch
    </x-site.section>
@empty
    <x-site.section>
        <p class="rounded-md border border-dashed border-brand-navy/20 p-4 text-sm text-brand-navy/50">This page has no sections yet.</p>
    </x-site.section>
@endforelse
