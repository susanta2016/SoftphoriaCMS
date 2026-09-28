@props(['transparent' => false, 'siteName' => null, 'tagline' => null, 'logo' => null])

@php
    $siteName = $siteName ?: config('app.name');

    $primaryMenu = \App\Models\Menu::query()
        ->where('slug', 'primary-navigation')
        ->where('is_active', true)
        ->with(['items' => fn ($query) => $query
            ->whereNull('parent_id')
            ->where('is_enabled', true)
            ->orderBy('sort_order'),
        ])
        ->first();

    // Links to a frontend feature that's switched off (Features Activation)
    // are dropped so the menu never points at a 404.
    $features = app(\App\Shared\Support\Features\Features::class);
    $navItems = ($primaryMenu?->items ?? collect())
        ->reject(fn ($item) => $features->hidesLink($item->resolvedUrl()))
        ->values();

    // Log In / account links are opt-in (Features Activation → "Header
    // Account Links", off by default); the login/account pages still work.
    $accountLinks = $features->enabled('header_account_links');

    // WEB-103: the redesign's single header call-to-action — editable in
    // Website Setup → General; defaults to "Let's Talk" → the Contact page.
    $generalSettings = app(\App\Shared\Services\Settings\SettingsRepository::class);
    $talkLabel = $generalSettings->get('general', 'header_cta_label') ?: "Let's Talk";
    // Only a real link is used — a full URL, a site path or an in-page
    // anchor. Anything else (e.g. a bare email address, which the browser
    // would treat as a relative page URL) falls back to the Contact page.
    $talkUrl = $generalSettings->get('general', 'header_cta_url');
    if (! is_string($talkUrl) || ! preg_match('~^(https?://|/|#.)~i', $talkUrl)) {
        $talkUrl = Route::has('contact.index') ? route('contact.index') : '#';
    }
@endphp

{{--
    WEB-103 redesign — a plain white bar: logo, primary nav (Menus →
    Primary Navigation) and a "Let's Talk" button. The WEB-102 gold
    utility bar and phone module were dropped to match the design; the
    Contact page still shows those contact details. Account links (a
    quiet "Log In" for guests, profile/logout when signed in) only show
    while Features Activation → "Header Account Links" is on.
--}}
<div class="fixed inset-x-0 top-0 z-30 w-full">
    <header
        data-site-header
        @if ($transparent) data-transparent-header @endif
        {{ $attributes->class([
            'w-full border-b transition-colors duration-200',
            'border-transparent bg-transparent' => $transparent,
            // Solid near-white with a hairline border; once scrolled (data-scrolled,
            // set by resources/js/app.js) it turns translucent with a backdrop blur.
            'border-brand-line bg-white data-scrolled:bg-white/85 data-scrolled:shadow-sm data-scrolled:backdrop-blur-md' => ! $transparent,
        ]) }}
    >
        <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-4 px-4 py-2.5 sm:px-6 lg:min-h-[4.5rem] lg:px-8">
            <a href="{{ route('home') }}" class="min-w-0 shrink rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-accent">
                @if ($logo)
                    {{-- Shown whole at its own aspect ratio (height cap only) — never cropped, so any uploaded logo keeps its full artwork. --}}
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk($logo->disk)->url($logo->path) }}"
                        alt="{{ $siteName }}"
                        class="h-11 w-auto max-w-full object-contain sm:h-12 lg:h-[3.25rem]"
                    >
                @else
                    <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" :on-dark="false" class="min-w-0"/>
                @endif
            </a>

            <nav aria-label="Primary" class="hidden lg:flex lg:items-center lg:gap-1 xl:gap-2">
                @foreach ($navItems as $item)
                    <a
                        href="{{ $item->resolvedUrl() ?? '#' }}"
                        class="rounded-md px-3 py-2 text-[0.9375rem] font-medium whitespace-nowrap text-brand-ink transition hover:text-brand-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-accent"
                    >
                        {{ $item->label }}
                    </a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-2 sm:gap-4 lg:border-l lg:border-brand-line lg:pl-6">
                {{--
                    AUTH-002: these were dead "#" links until login/register
                    existed. @guest/@auth here are the only auth-state-aware
                    markup in this component.
                --}}
                @if ($accountLinks)
                    @guest
                        <a href="{{ route('login') }}" class="hidden text-sm font-medium text-brand-navy transition hover:text-brand-accent sm:inline-block">
                            Log In
                        </a>
                    @else
                        @if (Route::has('account.profile.edit'))
                            <a href="{{ route('account.profile.edit') }}" class="hidden text-sm font-medium text-brand-navy transition hover:text-brand-accent sm:inline-block">
                                My Profile
                            </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-brand-navy transition hover:text-brand-accent">
                                Log Out
                            </button>
                        </form>
                    @endguest
                @endif

                <span class="hidden sm:block">
                    <x-site.button :href="$talkUrl" class="whitespace-nowrap">
                        {{ $talkLabel }} <x-site.arrow class="h-4 w-4"/>
                    </x-site.button>
                </span>

                <button
                    type="button"
                    data-mobile-menu-toggle
                    aria-label="Toggle menu"
                    aria-expanded="false"
                    aria-controls="mobile-menu"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-brand-navy/15 text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent lg:hidden"
                >
                    <svg data-mobile-menu-icon-open aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                    </svg>
                    <svg data-mobile-menu-icon-close aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hidden h-5 w-5">
                        <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" data-mobile-menu class="hidden max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-brand-line bg-white lg:hidden">
            <nav aria-label="Primary" class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6">
                @foreach ($navItems as $item)
                    <a href="{{ $item->resolvedUrl() ?? '#' }}" class="rounded-md px-3 py-3 text-base font-medium text-brand-navy transition hover:bg-brand-sky hover:text-brand-accent">
                        {{ $item->label }}
                    </a>
                @endforeach
            </nav>

            {{-- From sm up the header row already shows these (except Register), so a signed-in user's copy — or, with account links off, the phone-only Let's Talk button — would just be an empty bordered row there. --}}
            <div @class([
                'mx-auto flex max-w-7xl flex-col gap-1 border-t border-brand-navy/10 px-4 py-3 sm:px-6',
                'sm:hidden' => auth()->check() || ! $accountLinks,
            ])>
                @if ($accountLinks)
                    @guest
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-3 text-base font-medium text-brand-navy transition hover:bg-brand-sky hover:text-brand-accent sm:hidden">
                            Log In
                        </a>
                        <a href="{{ route('register') }}" class="rounded-md px-3 py-3 text-base font-medium text-brand-navy transition hover:bg-brand-sky hover:text-brand-accent">
                            Register
                        </a>
                    @else
                        @if (Route::has('account.profile.edit'))
                            <a href="{{ route('account.profile.edit') }}" class="rounded-md px-3 py-3 text-base font-medium text-brand-navy transition hover:bg-brand-sky hover:text-brand-accent sm:hidden">
                                My Profile
                            </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="sm:hidden">
                            @csrf
                            <button type="submit" class="w-full rounded-md px-3 py-2.5 text-left text-sm font-medium text-brand-navy transition hover:bg-brand-sky hover:text-brand-accent">
                                Log Out
                            </button>
                        </form>
                    @endguest
                @endif
                <x-site.button :href="$talkUrl" class="mt-2 sm:hidden">
                    {{ $talkLabel }} <x-site.arrow class="h-4 w-4"/>
                </x-site.button>
            </div>
        </div>
    </header>
</div>
