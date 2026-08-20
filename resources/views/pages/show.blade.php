{{--
    Public CMS page renderer — shares the real site header/footer chrome
    (x-layouts.site, same as home.blade.php) with every other public page,
    except the page currently selected as the Maintenance Page ($showChrome
    false — see PageContentRenderer), which always renders standalone.
    Also used by the admin-only preview route (PreviewPageController) via
    the same PageContentRenderer, so a preview shows exactly what visitors
    will see, plus the banner/title-prefix/noindex handled there for an
    unpublished page. See App\Shared\Support\Pages\PageContentRenderer.
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

    <style>
        .page-content { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
        main { max-width: 860px; margin: 0 auto; padding: 7rem 1.5rem 4rem; }
        .page-title { font-size: 2rem; margin: 1.5rem 0 0.5rem; }
        .page-summary { color: #4b5563; font-size: 1.125rem; margin: 0 0 2rem; }
        .featured-image { width: 100%; max-height: 420px; object-fit: cover; border-radius: 0.5rem; }
        section.block { margin: 2.5rem 0; }
        .hero { text-align: center; }
        .hero img { max-width: 100%; border-radius: 0.5rem; margin-bottom: 1rem; }
        .cta-button { display: inline-block; margin-top: 0.75rem; padding: 0.625rem 1.25rem; background: #111827; color: #fff; border-radius: 0.375rem; text-decoration: none; }
        .image-text { display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap; }
        .image-text img { max-width: 320px; border-radius: 0.5rem; flex: 1 1 280px; }
        .image-text .text { flex: 2 1 320px; }
        .quote { border-left: 4px solid #d1d5db; padding-left: 1rem; font-style: italic; font-size: 1.25rem; }
        .quote .attribution { display: block; margin-top: 0.5rem; font-style: normal; color: #6b7280; font-size: 0.875rem; }
        .faq-item { margin-bottom: 1rem; }
        .faq-item dt { font-weight: 600; }
        .faq-item dd { margin: 0.25rem 0 0; color: #4b5563; }
        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.75rem; }
        .gallery img { width: 100%; height: 140px; object-fit: cover; border-radius: 0.375rem; }
        .inert-notice { color: #9ca3af; font-size: 0.875rem; border: 1px dashed #d1d5db; padding: 1rem; border-radius: 0.375rem; }
        .disabled-badge { display: inline-block; margin-left: 0.5rem; font-size: 0.75rem; color: #9ca3af; }
    </style>

    <main class="page-content">
        @if ($page->featuredImage)
            <img class="featured-image" src="{{ \Illuminate\Support\Facades\Storage::disk($page->featuredImage->disk)->url($page->featuredImage->path) }}" alt="{{ $page->featuredImage->alt_text ?? $page->title }}">
        @endif

        <h1 class="page-title">{{ $page->title }}</h1>

        @if ($page->summary)
            <p class="page-summary">{{ $page->summary }}</p>
        @endif

        @forelse ($page->sections->where('is_enabled', true)->sortBy('sort_order') as $section)
            @php $content = $section->content_json ?? []; @endphp
            <section class="block">
                @switch($section->section_type)
                    @case('hero')
                        <div class="hero">
                            @if (!empty($content['media_id']) && ($media = \App\Models\Media::find($content['media_id'])))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) }}" alt="{{ $media->alt_text ?? '' }}">
                            @endif
                            @if (!empty($content['heading']))
                                <h2>{{ $content['heading'] }}</h2>
                            @endif
                            @if (!empty($content['subheading']))
                                <p>{{ $content['subheading'] }}</p>
                            @endif
                            @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                                <a class="cta-button" href="{{ $content['cta_url'] }}">{{ $content['cta_label'] }}</a>
                            @endif
                            @if (!empty($content['secondary_cta_label']) && !empty($content['secondary_cta_url']))
                                <a class="cta-button" href="{{ $content['secondary_cta_url'] }}" style="background:transparent;color:#111827;border:1px solid #111827;margin-left:0.5rem">{{ $content['secondary_cta_label'] }}</a>
                            @endif
                            @if (!empty($content['tertiary_label']) && !empty($content['tertiary_url']))
                                <p style="margin-top:0.75rem"><a href="{{ $content['tertiary_url'] }}">{{ $content['tertiary_label'] }}</a></p>
                            @endif
                        </div>
                        @break

                    @case('rich_text')
                        <div class="rich-text">{!! $content['body'] ?? '' !!}</div>
                        @break

                    @case('image_text')
                        <div class="image-text">
                            @if (!empty($content['media_id']) && ($media = \App\Models\Media::find($content['media_id'])))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) }}" alt="{{ $media->alt_text ?? '' }}">
                            @endif
                            <div class="text">{{ $content['text'] ?? '' }}</div>
                        </div>
                        @break

                    @case('faq')
                        <dl>
                            @foreach ($content['items'] ?? [] as $item)
                                <div class="faq-item">
                                    <dt>{{ $item['question'] ?? '' }}</dt>
                                    <dd>{{ $item['answer'] ?? '' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @break

                    @case('quote')
                        <blockquote class="quote">
                            {{ $content['quote'] ?? '' }}
                            @if (!empty($content['attribution']))
                                <span class="attribution">{{ $content['attribution'] }}</span>
                            @endif
                        </blockquote>
                        @break

                    @case('cta')
                        <div class="hero">
                            @if (!empty($content['heading']))
                                <h2>{{ $content['heading'] }}</h2>
                            @endif
                            @if (!empty($content['cta_label']) && !empty($content['cta_url']))
                                <a class="cta-button" href="{{ $content['cta_url'] }}">{{ $content['cta_label'] }}</a>
                            @endif
                        </div>
                        @break

                    @case('gallery')
                        <div class="gallery">
                            @foreach (\App\Models\Media::query()->whereIn('id', $content['media_ids'] ?? [])->get() as $media)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) }}" alt="{{ $media->alt_text ?? '' }}">
                            @endforeach
                        </div>
                        @break

                    @default
                        <p class="inert-notice">{{ $section->title ?: \App\Enums\PageSectionType::from($section->section_type)->getLabel() }} — no rendering yet for this block type.</p>
                @endswitch
            </section>
        @empty
            <p class="inert-notice">This page has no sections yet.</p>
        @endforelse
    </main>

    @if ($showChrome)
        <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
    @endif
</x-layouts.site>
