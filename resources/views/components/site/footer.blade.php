@props(['siteName' => null, 'tagline' => null])

@php
    $siteName = $siteName ?: config('app.name');
    $settings = app(\App\Shared\Services\Settings\SettingsRepository::class);

    $footerLogoMediaId = $settings->get('footer', 'logo_media_id');
    $footerLogo = $footerLogoMediaId ? \App\Models\Media::find($footerLogoMediaId) : null;
    // WEB-102: no fabricated Softphoria tagline here — an empty setting
    // just omits the subheading line rather than guessing at company copy.
    $footerSubheading = $settings->get('footer', 'subheading');

    $socialLinks = \App\Models\SocialLink::query()
        ->with('icon')
        ->where('is_enabled', true)
        ->orderBy('sort_order')
        ->get();

    $footerMenus = \App\Models\Menu::query()
        ->whereIn('slug', ['footer-navigation', 'footer-legal'])
        ->where('is_active', true)
        ->with(['items' => fn ($query) => $query
            ->whereNull('parent_id')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->with(['children' => fn ($children) => $children
                ->where('is_enabled', true)
                ->orderBy('sort_order')]),
        ])
        ->get()
        ->keyBy('slug');

    // Links to a switched-off frontend feature (Features Activation) are
    // dropped so the footer never points at a 404.
    $features = app(\App\Shared\Support\Features\Features::class);
    $visible = fn ($items) => $items->reject(fn ($item) => $features->hidesLink($item->resolvedUrl()))->values();
    $footerSections = $visible($footerMenus->get('footer-navigation')?->items ?? collect())
        ->each(fn ($section) => $section->setRelation('children', $visible($section->children)));
    $newsletterOn = $features->enabled('newsletter');
    // WEB-103: the bottom bar's small links (Privacy Policy, Terms, Sitemap)
    // — their own flat menu so they're editable in Menus like everything else.
    $legalLinks = $visible($footerMenus->get('footer-legal')?->items ?? collect());

    $footerBackgroundMediaId = $settings->get('footer', 'background_media_id');
    $footerBackgroundMedia = $footerBackgroundMediaId ? \App\Models\Media::find($footerBackgroundMediaId) : null;
    $footerBackgroundUrl = $footerBackgroundMedia
        ? \Illuminate\Support\Facades\Storage::disk($footerBackgroundMedia->disk)->url($footerBackgroundMedia->path)
        : null;

    $footerCopyrightText = $settings->get('footer', 'copyright_text')
        ?: '© {year} '.$siteName.'. All rights reserved.';
    $footerCopyrightText = str_replace('{year}', now()->year, $footerCopyrightText);
@endphp

{{--
    WEB-103 redesign — brand column, the Footer Navigation menu's groups
    (e.g. Services / Company / Expertise), a Follow Us column (social links
    + the newsletter signup), and a bottom bar with the copyright, the
    Footer Legal Links menu. (Cookie choices are reopened from the
    bottom-left Consent Preferences button — see x-site.cookie-consent.)
--}}
<footer
    class="relative isolate mt-auto overflow-hidden border-t border-brand-navy/10 bg-white bg-cover bg-center bg-no-repeat"
    @style([$footerBackgroundUrl ? "background-image: url('$footerBackgroundUrl')" : ''])
>
    <div class="mx-auto max-w-7xl px-4 pt-12 pb-6 sm:px-6 lg:px-8 lg:pt-14">
        <div class="grid grid-cols-2 gap-x-8 gap-y-10 sm:grid-cols-3 lg:grid-cols-12">
            <div class="col-span-2 sm:col-span-3 lg:col-span-4">
                <a href="{{ route('home') }}" class="inline-block">
                    @if ($footerLogo)
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk($footerLogo->disk)->url($footerLogo->path) }}"
                            alt="{{ $siteName }}"
                            class="h-14 w-auto max-w-[220px] object-contain"
                        >
                    @else
                        <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" :on-dark="false"/>
                    @endif
                </a>
                @if ($footerSubheading)
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-brand-navy/65">
                        {{ $footerSubheading }}
                    </p>
                @endif
            </div>

            @foreach ($footerSections as $section)
                <div class="lg:col-span-2">
                    <h3 class="text-sm font-semibold text-brand-navy">{{ $section->label }}</h3>
                    <ul class="mt-4 space-y-2.5">
                        @foreach ($section->children as $link)
                            <li><a href="{{ $link->resolvedUrl() ?? '#' }}" class="text-sm text-brand-navy/65 transition hover:text-brand-accent">{{ $link->label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div id="newsletter-subscribe" class="col-span-2 scroll-mt-24 sm:col-span-3 lg:col-span-2">
                @if ($socialLinks->isNotEmpty())
                    <h3 class="text-sm font-semibold text-brand-navy">{{ $settings->get('footer', 'social_heading') ?: 'Follow Us' }}</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($socialLinks as $link)
                            <a href="{{ $link->url }}" aria-label="{{ $link->label }}" target="_blank" rel="noopener" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-navy text-white transition hover:bg-brand-accent">
                                @if ($link->icon)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk($link->icon->disk)->url($link->icon->path) }}"
                                        alt=""
                                        class="object-contain"
                                    >
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                                        <path d="M10 13a5 5 0 0 0 7.07 0l2.83-2.83a5 5 0 0 0-7.07-7.07L11.5 4.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14 11a5 5 0 0 0-7.07 0L4.1 13.83a5 5 0 0 0 7.07 7.07l1.36-1.36" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($newsletterOn)
                <h3 @class(['text-sm font-semibold text-brand-navy', 'mt-6' => $socialLinks->isNotEmpty()])>{{ $settings->get('footer', 'newsletter_heading') ?: 'Newsletter' }}</h3>
                @if (session('newsletter_status'))
                    <p class="mt-3 rounded-md border border-brand-accent/30 bg-brand-sky px-3 py-2.5 text-sm text-brand-navy">
                        {{ session('newsletter_status') }}
                    </p>
                @else
                    <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-3 max-w-sm">
                        @csrf
                        {{-- Honeypot — see NewsletterController::subscribe(). --}}
                        <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
                            <label for="footer-newsletter-hp_website">Website</label>
                            <input type="text" id="footer-newsletter-hp_website" name="hp_website" tabindex="-1" autocomplete="off">
                        </div>
                        <div @class([
                            'flex overflow-hidden rounded-md border bg-white',
                            'border-red-400' => $errors->has('email'),
                            'border-brand-navy/20' => ! $errors->has('email'),
                        ])>
                            <label for="footer-newsletter-email" class="sr-only">Email address</label>
                            <input
                                id="footer-newsletter-email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="Your email"
                                required
                                class="w-full min-w-0 border-0 px-3 py-2 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:outline-none"
                            >
                            <button type="submit" aria-label="Subscribe" class="shrink-0 bg-brand-accent px-3 py-2 text-white transition hover:bg-brand-accent-dark">
                                <x-site.arrow class="h-4 w-4"/>
                            </button>
                        </div>
                        @error('email')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
                @endif
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-3 border-t border-brand-navy/10 pt-6 text-xs text-brand-navy/60 sm:flex-row sm:items-center sm:justify-between">
            <p>{{ $footerCopyrightText }}</p>
            <ul class="flex flex-wrap items-center gap-x-5 gap-y-2">
                @foreach ($legalLinks as $link)
                    <li><a href="{{ $link->resolvedUrl() ?? '#' }}" class="transition hover:text-brand-accent">{{ $link->label }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>
