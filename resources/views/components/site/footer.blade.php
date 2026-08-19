@props(['siteName' => 'All The Things Light', 'tagline' => null])

@php
    $settings = app(\App\Shared\Services\Settings\SettingsRepository::class);

    $footerLogoMediaId = $settings->get('footer', 'logo_media_id');
    $footerLogo = $footerLogoMediaId ? \App\Models\Media::find($footerLogoMediaId) : null;
    $footerSubheading = $settings->get('footer', 'subheading')
        ?: 'A creative home for music, writing, reflection, thinking, and community.';

    $socialLinks = \App\Models\SocialLink::query()
        ->with('icon')
        ->where('is_enabled', true)
        ->orderBy('sort_order')
        ->get();

    $footerMenu = \App\Models\Menu::query()
        ->where('slug', 'footer-navigation')
        ->where('is_active', true)
        ->with(['items' => fn ($query) => $query
            ->whereNull('parent_id')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->with(['children' => fn ($children) => $children
                ->where('is_enabled', true)
                ->orderBy('sort_order')]),
        ])
        ->first();

    $footerSections = $footerMenu?->items ?? collect();

    $footerBackgroundMediaId = $settings->get('footer', 'background_media_id');
    $footerBackgroundMedia = $footerBackgroundMediaId ? \App\Models\Media::find($footerBackgroundMediaId) : null;
    $footerBackgroundUrl = $footerBackgroundMedia
        ? \Illuminate\Support\Facades\Storage::disk($footerBackgroundMedia->disk)->url($footerBackgroundMedia->path)
        : null;

    $footerCopyrightText = $settings->get('footer', 'copyright_text')
        ?: '© {year} '.$siteName.'. All rights reserved.';
    $footerCopyrightText = str_replace('{year}', now()->year, $footerCopyrightText);
@endphp

<footer
    class="relative isolate mt-auto overflow-hidden bg-cover bg-center bg-no-repeat"
    @style([$footerBackgroundUrl ? "background-image: url('$footerBackgroundUrl')" : ''])
>
    <div class="mx-auto max-w-7xl px-4 pt-14 pb-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-x-8 gap-y-10 sm:grid-cols-3 lg:grid-cols-5">
            <div class="col-span-2 sm:col-span-3 lg:col-span-1">
                @if ($footerLogo)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk($footerLogo->disk)->url($footerLogo->path) }}"
                        alt="{{ $siteName }}"
                        class="h-10 w-auto object-contain"
                    >
                @else
                    <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" :on-dark="false"/>
                @endif
                <p class="mt-4 max-w-xs text-sm text-brand-navy/70">
                    {{ $footerSubheading }}
                </p>
                <div class="mt-5 flex gap-2">
                    @foreach ($socialLinks as $link)
                        <a href="{{ $link->url }}" aria-label="{{ $link->label }}" target="_blank" rel="noopener" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-navy text-white transition hover:bg-brand-gold">
                            @if ($link->icon)
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::disk($link->icon->disk)->url($link->icon->path) }}"
                                    alt=""
                                    class="h-4 w-4 object-contain"
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
            </div>

            @foreach ($footerSections as $section)
                <div>
                    <h3 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">{{ $section->label }} <span class="text-brand-gold" aria-hidden="true">✦</span></h3>
                    <ul class="mt-4 space-y-2.5">
                        @foreach ($section->children as $link)
                            <li><a href="{{ $link->resolvedUrl() ?? '#' }}" class="text-sm text-brand-navy/75 transition hover:text-brand-gold">{{ $link->label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="col-span-2 sm:col-span-3 lg:col-span-1">
                <h3 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Join Our Newsletter</h3>
                <p class="mt-4 text-sm text-brand-navy/75">Sign up for updates from {{ $siteName }}.</p>
                <div class="mt-3 flex overflow-hidden rounded-md border border-brand-navy/20 bg-white">
                    <label for="footer-newsletter-email" class="sr-only">Email address</label>
                    <input
                        id="footer-newsletter-email"
                        type="email"
                        placeholder="Enter your email"
                        disabled
                        class="w-full min-w-0 border-0 px-3 py-2.5 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:outline-none disabled:cursor-not-allowed disabled:bg-white"
                    >
                    <button
                        type="button"
                        disabled
                        title="Newsletter sign-up is coming soon"
                        class="shrink-0 cursor-not-allowed bg-brand-gold px-4 py-2.5 text-sm font-semibold text-white opacity-70"
                    >
                        Subscribe
                    </button>
                </div>
                <p class="mt-4 text-xs text-brand-navy/60">{{ $footerCopyrightText }}</p>
            </div>
        </div>
    </div>
</footer>
