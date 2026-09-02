@props(['transparent' => false, 'siteName' => 'All The Things Light', 'tagline' => null, 'logo' => null])

@php
    $primaryMenu = \App\Models\Menu::query()
        ->where('slug', 'primary-navigation')
        ->where('is_active', true)
        ->with(['items' => fn ($query) => $query
            ->whereNull('parent_id')
            ->where('is_enabled', true)
            ->orderBy('sort_order'),
        ])
        ->first();

    $navItems = $primaryMenu?->items ?? collect();
@endphp

<header
    @if ($transparent) data-transparent-header @endif
    {{ $attributes->class([
        'fixed inset-x-0 top-0 z-30 w-full transition-colors duration-200',
        'bg-transparent' => $transparent,
        'bg-white shadow-sm' => ! $transparent,
    ]) }}
>
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-3 gap-y-3 px-4 py-4 sm:gap-x-6 sm:px-6 lg:flex-nowrap lg:px-8">
        <a href="{{ route('home') }}" class="min-w-0 shrink">
            @if ($logo)
                {{-- The uploaded lockup has a lot of empty canvas above/below the ring+wordmark, so a plain height cap renders it unreadably small. Cropping to the artwork's own aspect ratio keeps the header compact while showing it at a legible size. --}}
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk($logo->disk)->url($logo->path) }}"
                    alt="{{ $siteName }}"
                    class="h-12 w-auto object-cover sm:h-14"
                    style="aspect-ratio: 4.7 / 1; object-position: 50% 45%;"
                >
            @else
                <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" :on-dark="false" class="min-w-0"/>
            @endif
        </a>

        {{--
            Nav/icon/button colors are deliberately NOT conditioned on
            $transparent — always the navy palette, matching every other
            page. Home's own hero art (a bright sky/sun image, not a dark
            overlay) made the old $transparent ? white : navy branches here
            render near-invisible white-on-white text against it. $transparent
            still only controls the header's own background fill below
            (bg-transparent lets that hero image show through at the very
            top of Home), which is an unrelated, still-correct effect.
        --}}
        <nav aria-label="Primary" data-primary-nav class="hidden transition-opacity duration-200 lg:flex lg:items-center lg:gap-7">
            @foreach ($navItems as $item)
                <a
                    href="{{ $item->resolvedUrl() ?? '#' }}"
                    class="text-sm font-medium whitespace-nowrap text-brand-navy transition hover:text-brand-gold"
                >
                    {{ $item->label }}
                </a>
            @endforeach
        </nav>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            {{--
                In-place expand/collapse search, attached to this exact icon
                slot — closed state is visually identical to the previous
                dead "#" link (same size/position/breakpoint), so nothing
                about the header's own closed-state look changes. Expanding
                is a pure width transition on data-search-panel (grows
                leftward via `absolute right-0`, so it overlays rather than
                pushes Cart/Log In/Enter Here out of flow — those controls
                are never reflowed or pushed off-screen, only briefly
                covered by the search panel itself while it's open, same as
                any standard expand-in-place search pattern). See
                resources/js/app.js's [data-search-control] block for the
                open/close/debounced-autocomplete/keyboard behavior — shared
                with the mobile-menu instance further down this file.
            --}}
            <div class="relative hidden sm:block" data-search-control data-search-variant="desktop">
                <button
                    type="button"
                    data-search-toggle
                    aria-label="Search"
                    aria-expanded="false"
                    aria-controls="header-search-panel"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-brand-navy/20 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                    </svg>
                </button>

                <div
                    id="header-search-panel"
                    data-search-panel
                    hidden
                    class="absolute top-1/2 right-0 z-20 h-9 w-0 -translate-y-1/2 overflow-hidden rounded-md border border-brand-navy/20 bg-white shadow-lg transition-[width] duration-200 ease-out"
                >
                    <form role="search" method="GET" action="{{ route('search.index') }}" class="flex h-9 w-64 items-center gap-1 px-1.5 sm:w-72" style="max-width: calc(100vw - 6rem);" data-search-form>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0 text-brand-navy/40"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/></svg>
                        <label for="header-search-input" class="sr-only">Search</label>
                        <input
                            id="header-search-input"
                            type="search"
                            name="q"
                            autocomplete="off"
                            role="combobox"
                            aria-expanded="false"
                            aria-controls="header-search-suggestions"
                            aria-autocomplete="list"
                            placeholder="Search…"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:ring-0 focus:outline-none"
                            data-search-input
                        >
                        <button type="submit" aria-label="Submit search" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-brand-navy/50 transition hover:text-brand-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <button type="button" data-search-close aria-label="Close search" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-brand-navy/50 transition hover:text-brand-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                        </button>
                    </form>
                </div>

                {{--
                    Deliberately a sibling of data-search-panel, not a child
                    of it — the panel has overflow-hidden for its own width
                    transition, which would clip a dropdown nested inside it.
                --}}
                <div
                    id="header-search-suggestions"
                    data-search-suggestions
                    role="listbox"
                    aria-label="Search suggestions"
                    hidden
                    class="absolute top-full right-0 z-20 mt-2 w-72 overflow-hidden rounded-md border border-brand-navy/10 bg-white shadow-xl sm:w-80"
                ></div>
            </div>

            {{--
                A Pro Member's whole catalogue access is already included
                with their subscription (see MusicController::purchaseState's
                'included' state) — there is never anything for them to buy,
                so the cart icon (and whatever it might still be holding from
                before they subscribed) stays hidden rather than pointing at
                an empty/irrelevant page.
            --}}
            @unless (auth()->user()?->hasActiveMembership())
                <a
                    href="{{ route('cart.show') }}"
                    aria-label="Cart"
                    class="relative inline-flex h-9 w-9 items-center justify-center rounded-md border border-brand-navy/20 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                        <path d="M3 4h2l1.6 9.6a2 2 0 0 0 2 1.7h8a2 2 0 0 0 2-1.7L20 8H6" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="9.5" cy="19.5" r="1.3"/>
                        <circle cx="16.5" cy="19.5" r="1.3"/>
                    </svg>
                    @php($cartCount = \App\Modules\Music\Support\CartSession::count())
                    @if ($cartCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-gold px-1 text-[10px] font-semibold text-white">{{ $cartCount }}</span>
                    @endif
                </a>
            @endunless
            @auth
                <a
                    href="{{ route('account.dashboard') }}"
                    class="hidden rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold sm:inline-block"
                >
                    My Account
                </a>
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                    @csrf
                    <button
                        type="submit"
                        aria-label="Log out"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-brand-navy/20 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </form>
            @else
                <a
                    href="{{ route('login') }}"
                    class="hidden rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold sm:inline-block"
                >
                    Log In
                </a>
                <a href="{{ route('register.show') }}" class="inline-flex items-center gap-1.5 rounded-md bg-brand-gold px-3 py-2 text-sm font-semibold whitespace-nowrap text-white transition hover:bg-brand-gold-light sm:px-4">
                    Enter Here <span aria-hidden="true">→</span>
                </a>
            @endauth

            <button
                type="button"
                data-mobile-menu-toggle
                aria-label="Toggle menu"
                aria-expanded="false"
                aria-controls="mobile-menu"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-brand-navy/20 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold lg:hidden"
            >
                <svg data-mobile-menu-icon-open xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                </svg>
                <svg data-mobile-menu-icon-close xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hidden h-4 w-4">
                    <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>

    <div id="mobile-menu" data-mobile-menu class="hidden border-t border-brand-navy/10 bg-white lg:hidden">
        <nav aria-label="Primary" class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6">
            @foreach ($navItems as $item)
                <a href="{{ $item->resolvedUrl() ?? '#' }}" class="rounded-md px-3 py-2.5 text-sm font-medium text-brand-navy transition hover:bg-brand-gold/10 hover:text-brand-gold">
                    {{ $item->label }}
                </a>
            @endforeach
        </nav>

        {{--
            Search and Log In are already visible as standalone header
            controls from the sm breakpoint up (classes above), so they'd
            be redundant here at sm+. Below sm — genuinely small/mobile
            screens — those controls are hidden entirely, so this is their
            only way in: surfaced here instead, alongside the nav links.
        --}}
        <div class="mx-auto flex max-w-7xl flex-col gap-1 border-t border-brand-navy/10 px-4 py-3 sm:hidden" data-search-control data-search-variant="mobile">
            <button
                type="button"
                data-search-toggle
                aria-expanded="false"
                aria-controls="mobile-search-panel"
                class="flex items-center gap-2 rounded-md px-3 py-2.5 text-left text-sm font-medium text-brand-navy transition hover:bg-brand-gold/10 hover:text-brand-gold"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                </svg>
                Search
            </button>

            <div id="mobile-search-panel" data-search-panel hidden class="px-3 pb-2">
                <form role="search" method="GET" action="{{ route('search.index') }}" class="flex items-center gap-1.5 rounded-md border border-brand-navy/20 px-2 py-1.5" data-search-form>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0 text-brand-navy/40"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/></svg>
                    <label for="mobile-search-input" class="sr-only">Search</label>
                    <input
                        id="mobile-search-input"
                        type="search"
                        name="q"
                        autocomplete="off"
                        role="combobox"
                        aria-expanded="false"
                        aria-controls="mobile-search-suggestions"
                        aria-autocomplete="list"
                        placeholder="Search…"
                        class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:ring-0 focus:outline-none"
                        data-search-input
                    >
                    <button type="submit" aria-label="Submit search" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-brand-navy/50 transition hover:text-brand-gold">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <button type="button" data-search-close aria-label="Close search" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-brand-navy/50 transition hover:text-brand-gold">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                    </button>
                </form>

                <div
                    id="mobile-search-suggestions"
                    data-search-suggestions
                    role="listbox"
                    aria-label="Search suggestions"
                    hidden
                    class="mt-2 overflow-hidden rounded-md border border-brand-navy/10 bg-white shadow"
                ></div>
            </div>
        </div>

        <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 pb-3 sm:hidden">
            @auth
                <a href="{{ route('account.dashboard') }}" class="rounded-md px-3 py-2.5 text-sm font-medium text-brand-navy transition hover:bg-brand-gold/10 hover:text-brand-gold">
                    My Account
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-left text-sm font-medium text-brand-navy transition hover:bg-red-50 hover:text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Log Out
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-md px-3 py-2.5 text-sm font-medium text-brand-navy transition hover:bg-brand-gold/10 hover:text-brand-gold">
                    Log In
                </a>
                <a href="{{ route('register.show') }}" class="mt-1 flex items-center justify-center gap-1.5 rounded-md bg-brand-gold px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-gold-light">
                    Enter Here <span aria-hidden="true">→</span>
                </a>
            @endauth
        </div>
    </div>
</header>
