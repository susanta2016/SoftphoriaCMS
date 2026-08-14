@props(['siteName', 'tagline' => null, 'onDark' => true])

<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" fill="none" class="h-8 w-8 shrink-0" aria-hidden="true">
        <circle cx="20" cy="20" r="16" stroke="currentColor" stroke-width="2.5" class="text-brand-gold"/>
        <circle cx="20" cy="4" r="1.5" fill="currentColor" class="text-brand-gold"/>
    </svg>
    <span class="flex min-w-0 flex-col leading-tight">
        <span class="truncate font-serif text-base tracking-wide sm:text-lg {{ $onDark ? 'text-white' : 'text-brand-navy' }}">
            {{ $siteName }}
        </span>
        @if ($tagline)
            <span class="truncate text-[10px] font-semibold tracking-[0.15em] text-brand-gold uppercase">
                {{ $tagline }}
            </span>
        @endif
    </span>
</span>
