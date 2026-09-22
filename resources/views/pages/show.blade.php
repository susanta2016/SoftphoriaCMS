{{--
    Public CMS page renderer — shares the real site header/footer chrome
    (x-layouts.site, same as home.blade.php) with every other public page,
    except the page currently selected as the Maintenance Page ($showChrome
    false — see PageContentRenderer), which always renders standalone.
    Also used by the admin-only preview route (PreviewPageController) via
    the same PageContentRenderer, so a preview shows exactly what visitors
    will see, plus the banner/title-prefix/noindex handled there for an
    unpublished page. See App\Shared\Support\Pages\PageContentRenderer.

    WEB-101: rebuilt on the Softphoria public design system (Tailwind
    utilities + resources/views/components/site/*) in place of the old
    inline <style> block. Every section type that worked before still
    works exactly the same way; gallery additionally understands the new
    structured content_json.gallery_items shape (item E) while still
    rendering old content_json.media_ids content unchanged, and contact_form
    (item D) now renders the shared contact form instead of falling through
    to the inert-notice placeholder.
--}}
<x-layouts.site :seo="$seo">
    @unless ($page->status->value === 'published')
        <div class="bg-amber-50 px-4 py-3 text-center text-sm font-semibold text-amber-800">
            Preview only — this page is currently <strong>{{ $page->status->getLabel() }}</strong> and is not visible to the public.
        </div>
    @endunless

    @if ($showChrome)
        <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>
    @endif

    <main class="pt-24 pb-4 sm:pt-28">
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            @if ($page->featuredImage)
                <img
                    class="max-h-[420px] w-full rounded-lg object-cover"
                    src="{{ \Illuminate\Support\Facades\Storage::disk($page->featuredImage->disk)->url($page->featuredImage->path) }}"
                    alt="{{ $page->featuredImage->alt_text ?? $page->title }}"
                >
            @endif

            <h1 class="mt-6 text-3xl font-bold text-brand-navy sm:text-4xl">{{ $page->title }}</h1>

            @if ($page->summary)
                <p class="mt-2 text-lg text-brand-navy/70">{{ $page->summary }}</p>
            @endif
        </div>

        @forelse ($page->sections->where('is_enabled', true)->sortBy('sort_order') as $section)
            @php $content = $section->content_json ?? []; @endphp

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
                                    <a href="{{ $content['tertiary_url'] }}" class="text-sm font-medium text-brand-navy underline decoration-brand-navy/30 underline-offset-2 hover:text-brand-gold">{{ $content['tertiary_label'] }}</a>
                                </p>
                            @endif
                        </div>
                        @break

                    @case('rich_text')
                        <div class="max-w-none text-brand-navy [&_a]:text-brand-gold [&_a]:underline [&_h2]:mt-6 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-brand-navy [&_h3]:mt-4 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-brand-navy [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6">
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
                        <blockquote class="border-l-4 border-brand-gold pl-4 text-xl text-brand-navy italic">
                            {{ $content['quote'] ?? '' }}
                            @if (!empty($content['attribution']))
                                <span class="mt-2 block text-sm font-normal text-brand-navy/60 not-italic">{{ $content['attribution'] }}</span>
                            @endif
                        </blockquote>
                        @break

                    @case('cta')
                        <div class="text-center">
                            @if (!empty($content['heading']))
                                <h2 class="text-2xl font-bold text-brand-navy">{{ $content['heading'] }}</h2>
                            @endif
                            @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                                <div class="mt-5">
                                    <x-site.button :href="$content['cta_url']">{{ $content['cta_label'] }}</x-site.button>
                                </div>
                            @endif
                        </div>
                        @break

                    @case('gallery')
                        {{--
                            WEB-101 item E: prefer the new structured
                            content_json.gallery_items ({media_id, title,
                            description, url} per item — see PageForm's
                            Gallery repeater). Older sections saved before
                            this upgrade only have the flat
                            content_json.media_ids array, which keeps
                            rendering exactly as before.
                        --}}
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            @if (!empty($content['gallery_items']))
                                @foreach ($content['gallery_items'] as $item)
                                    @continue(empty($item['media_id']))
                                    <x-site.portfolio-item
                                        :media="\App\Models\Media::find($item['media_id'])"
                                        :title="$item['title'] ?? null"
                                        :description="$item['description'] ?? null"
                                        :url="$item['url'] ?? null"
                                    />
                                @endforeach
                            @else
                                @foreach (\App\Models\Media::query()->whereIn('id', $content['media_ids'] ?? [])->get() as $media)
                                    <x-site.portfolio-item :media="$media"/>
                                @endforeach
                            @endif
                        </div>
                        @break

                    @case('contact_form')
                        {{--
                            WEB-101 item D: reuses the exact same form/route/
                            controller as the dedicated Contact page — see
                            resources/views/components/site/contact-form.blade.php.
                            The section's own "Admin label" (title) doubles
                            as an optional on-page heading; no new content_json
                            field was needed for this.
                        --}}
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
    </main>

    @if ($showChrome)
        <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
    @endif
</x-layouts.site>
