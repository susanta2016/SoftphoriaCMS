{{-- The text-only brand lockup shown whenever no logo image is uploaded (Website Setup → General). WEB-103: restyled to the redesign's bold sans wordmark with a blue "S" mark. --}}
@props(['siteName', 'tagline' => null, 'onDark' => true])

<span {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none" class="h-8 w-8 shrink-0 text-brand-accent" aria-hidden="true">
        <path d="M24 8.5c-1.6-2.4-4.5-3.5-7.7-3.5C11.6 5 8 7.6 8 11.2c0 7.8 16 4.7 16 12.3 0 3.4-3.6 5.9-8.4 5.9-3.4 0-6.4-1.3-8.1-3.7" stroke="currentColor" stroke-width="4.2" stroke-linecap="round"/>
    </svg>
    <span class="flex min-w-0 flex-col leading-tight">
        <span class="truncate text-lg font-extrabold tracking-tight sm:text-xl {{ $onDark ? 'text-white' : 'text-brand-navy' }}">
            {{ $siteName }}
        </span>
        @if ($tagline)
            <span class="truncate text-[10px] font-medium {{ $onDark ? 'text-white/70' : 'text-brand-navy/70' }}">
                {{ $tagline }}
            </span>
        @endif
    </span>
</span>
