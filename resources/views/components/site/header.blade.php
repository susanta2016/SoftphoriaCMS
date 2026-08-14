@props(['transparent' => false, 'siteName' => 'All The Things Light', 'tagline' => null, 'logo' => null])

@php
    $navItems = [
        'About' => '#',
        'Music' => '#',
        'Podcast' => '#',
        'Poetry/Prose' => '#',
        'Inspirational Resources' => '#',
        'Contact' => '#',
    ];
@endphp

<header
    {{ $attributes->class([
        'relative z-20 w-full',
        'bg-transparent' => $transparent,
        'bg-brand-navy' => ! $transparent,
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
                <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" class="min-w-0"/>
            @endif
        </a>

        <nav aria-label="Primary" class="hidden lg:flex lg:items-center lg:gap-7">
            @foreach ($navItems as $label => $href)
                <a href="{{ $href }}" class="text-sm font-medium whitespace-nowrap text-white/90 transition hover:text-brand-gold-light">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <a href="#" aria-label="Search" class="hidden h-9 w-9 items-center justify-center rounded-md border border-white/30 text-white/90 transition hover:border-white hover:text-white sm:inline-flex">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="#" class="hidden rounded-md border border-white/30 px-4 py-2 text-sm font-medium text-white transition hover:border-white sm:inline-block">
                Log In
            </a>
            <a href="#" class="inline-flex items-center gap-1.5 rounded-md bg-brand-gold px-3 py-2 text-sm font-semibold whitespace-nowrap text-white transition hover:bg-brand-gold-light sm:px-4">
                Enter Here <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>

    <nav aria-label="Primary" class="-mx-4 flex gap-6 overflow-x-auto px-4 pb-3 [scrollbar-width:none] lg:hidden [&::-webkit-scrollbar]:hidden">
        @foreach ($navItems as $label => $href)
            <a href="{{ $href }}" class="shrink-0 text-sm font-medium whitespace-nowrap text-white/90 transition hover:text-brand-gold-light">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</header>
