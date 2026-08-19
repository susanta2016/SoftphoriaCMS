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
    {{ $attributes->class([
        'relative z-20 w-full',
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
                <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" :on-dark="$transparent" class="min-w-0"/>
            @endif
        </a>

        <nav aria-label="Primary" class="hidden lg:flex lg:items-center lg:gap-7">
            @foreach ($navItems as $item)
                <a
                    href="{{ $item->resolvedUrl() ?? '#' }}"
                    @class([
                        'text-sm font-medium whitespace-nowrap transition',
                        'text-white/90 hover:text-brand-gold-light' => $transparent,
                        'text-brand-navy hover:text-brand-gold' => ! $transparent,
                    ])
                >
                    {{ $item->label }}
                </a>
            @endforeach
        </nav>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <a
                href="#"
                aria-label="Search"
                @class([
                    'hidden h-9 w-9 items-center justify-center rounded-md border transition sm:inline-flex',
                    'border-white/30 text-white/90 hover:border-white hover:text-white' => $transparent,
                    'border-brand-navy/20 text-brand-navy hover:border-brand-gold hover:text-brand-gold' => ! $transparent,
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                </svg>
            </a>
            <a
                href="#"
                @class([
                    'hidden rounded-md border px-4 py-2 text-sm font-medium transition sm:inline-block',
                    'border-white/30 text-white hover:border-white' => $transparent,
                    'border-brand-navy/20 text-brand-navy hover:border-brand-gold hover:text-brand-gold' => ! $transparent,
                ])
            >
                Log In
            </a>
            <a href="#" class="inline-flex items-center gap-1.5 rounded-md bg-brand-gold px-3 py-2 text-sm font-semibold whitespace-nowrap text-white transition hover:bg-brand-gold-light sm:px-4">
                Enter Here <span aria-hidden="true">→</span>
            </a>

            <button
                type="button"
                data-mobile-menu-toggle
                aria-label="Toggle menu"
                aria-expanded="false"
                aria-controls="mobile-menu"
                @class([
                    'inline-flex h-9 w-9 items-center justify-center rounded-md border transition lg:hidden',
                    'border-white/30 text-white/90 hover:border-white hover:text-white' => $transparent,
                    'border-brand-navy/20 text-brand-navy hover:border-brand-gold hover:text-brand-gold' => ! $transparent,
                ])
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
    </div>
</header>
