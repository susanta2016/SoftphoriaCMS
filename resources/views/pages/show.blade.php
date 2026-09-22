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

    WEB-102: the actual section-by-section rendering was extracted into
    x-site.sections so the Homepage (resources/views/home.blade.php) can
    reuse the exact same section types instead of a second copy.
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

        <x-site.sections :sections="$page->sections->where('is_enabled', true)->sortBy('sort_order')"/>
    </main>

    @if ($showChrome)
        <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
    @endif
</x-layouts.site>
