{{--
    WEB-102 browser-verification pass — the closed set of generic decorative
    icons defined by App\Shared\Support\Pages\GalleryItemIcons. Every path
    is hand-drawn inline SVG (no icon library dependency); an unknown/blank
    `name` renders nothing.
--}}
@props(['name' => null])

@php $classes = $attributes->get('class') ?: 'h-6 w-6'; @endphp

@switch($name)
    @case('design')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.4-.3-.4-.5-.9-.5-1.4 0-1.1.9-2 2-2h2a4 4 0 0 0 4-4 8.98 8.98 0 0 0-9-7Z" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="7.5" cy="10.5" r="1.2" fill="currentColor" stroke="none"/>
            <circle cx="11" cy="7" r="1.2" fill="currentColor" stroke="none"/>
            <circle cx="15.5" cy="8.5" r="1.2" fill="currentColor" stroke="none"/>
        </svg>
        @break

    @case('code')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="m9 8-4 4 4 4M15 8l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('mobile')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <rect x="7" y="2.5" width="10" height="19" rx="2"/>
            <path d="M11 18.5h2" stroke-linecap="round"/>
        </svg>
        @break

    @case('search')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <circle cx="10.5" cy="10.5" r="6.5"/>
            <path d="m20 20-4.35-4.35" stroke-linecap="round"/>
        </svg>
        @break

    @case('plan')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <rect x="5" y="4" width="14" height="17" rx="1.5"/>
            <path d="M9 2.5h6v3H9z"/>
            <path d="m8.5 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('gear')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <circle cx="12" cy="12" r="3.2"/>
            <path d="M12 2.5v2.3M12 19.2v2.3M4.6 6.6l1.6 1.6M17.8 15.8l1.6 1.6M2.5 12h2.3M19.2 12h2.3M4.6 17.4l1.6-1.6M17.8 8.2l1.6-1.6" stroke-linecap="round"/>
        </svg>
        @break

    @case('rocket')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M12 2.5c3 2 4.5 5.3 4.5 9 0 2-.6 3.8-1.6 5.2L12 19l-2.9-2.3C8.1 15.3 7.5 13.5 7.5 11.5c0-3.7 1.5-7 4.5-9Z" stroke-linejoin="round"/>
            <circle cx="12" cy="10.5" r="1.6"/>
            <path d="M8.5 16.5 6 21l4-1.7M15.5 16.5 18 21l-4-1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
@endswitch
