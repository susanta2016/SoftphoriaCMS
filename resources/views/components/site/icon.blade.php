{{--
    WEB-102 browser-verification pass — the closed set of generic decorative
    icons defined by App\Shared\Support\Pages\GalleryItemIcons. Every path
    is hand-drawn inline SVG (no icon library dependency); an unknown/blank
    `name` renders nothing.
--}}
@props(['name' => null, 'mono' => false])

@php
    $classes = $attributes->get('class') ?: 'h-6 w-6';
    // WEB-103: technology brand logos (App\Shared\Support\Pages\TechLogos)
    // share this component — brand-colored by default, or currentColor
    // with `mono` (e.g. white on a dark article thumbnail).
    $logo = \App\Shared\Support\Pages\TechLogos::find($name);
@endphp

@if ($logo)
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="{{ $mono ? 'currentColor' : $logo['color'] }}" class="{{ $classes }}" role="img" aria-label="{{ $logo['label'] }}">
        <path d="{{ $logo['path'] }}"/>
    </svg>
@endif

@switch($logo ? null : $name)
    @case('monitor')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <rect x="2.5" y="4" width="19" height="12.5" rx="1.5"/>
            <path d="M8.5 20.5h7M12 16.5v4" stroke-linecap="round"/>
        </svg>
        @break

    @case('cart')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M2.5 3.5h2.6l2.3 11.2a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2l1.4-7.2H6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9.5" cy="19.8" r="1.4"/>
            <circle cx="17" cy="19.8" r="1.4"/>
        </svg>
        @break

    @case('cloud')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M7 18.5h10.5a4 4 0 0 0 .6-7.95A6 6 0 0 0 6.4 9.1 4.75 4.75 0 0 0 7 18.5Z" stroke-linejoin="round"/>
        </svg>
        @break

    @case('nodes')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <circle cx="6" cy="12" r="2.8"/>
            <circle cx="18" cy="5.5" r="2.8"/>
            <circle cx="18" cy="18.5" r="2.8"/>
            <path d="m8.5 10.7 7-3.9M8.5 13.3l7 3.9"/>
        </svg>
        @break

    @case('document')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M14 2.5H6.5A1.5 1.5 0 0 0 5 4v16a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 20V7.5l-5-5Z" stroke-linejoin="round"/>
            <path d="M14 2.5v5h5M8.5 12.5h7M8.5 16h7M8.5 9h3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('users')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <circle cx="9" cy="8" r="3.5"/>
            <path d="M2.5 20a6.5 6.5 0 0 1 13 0" stroke-linecap="round"/>
            <path d="M15.5 4.7a3.5 3.5 0 0 1 0 6.6M18 14.2a6.5 6.5 0 0 1 3.5 5.8" stroke-linecap="round"/>
        </svg>
        @break

    @case('cog')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M10.3 2.5h3.4l.5 2.6a7.5 7.5 0 0 1 1.9 1.1l2.5-.9 1.7 3-2 1.7a7.6 7.6 0 0 1 0 2.2l2 1.7-1.7 3-2.5-.9a7.5 7.5 0 0 1-1.9 1.1l-.5 2.6h-3.4l-.5-2.6a7.5 7.5 0 0 1-1.9-1.1l-2.5.9-1.7-3 2-1.7a7.6 7.6 0 0 1 0-2.2l-2-1.7 1.7-3 2.5.9a7.5 7.5 0 0 1 1.9-1.1l.5-2.6Z" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        @break

    @case('link')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M10 14a4.5 4.5 0 0 0 6.4 0l3.2-3.2a4.5 4.5 0 0 0-6.4-6.4L11.8 5.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3.2 3.2a4.5 4.5 0 0 0 6.4 6.4l1.4-1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('headset')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M4 14v-2a8 8 0 0 1 16 0v2" stroke-linecap="round"/>
            <rect x="3" y="13" width="4" height="6" rx="1.5"/>
            <rect x="17" y="13" width="4" height="6" rx="1.5"/>
            <path d="M19 19c0 1.7-2 2.5-5 2.5" stroke-linecap="round"/>
        </svg>
        @break

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

    @case('sparkles')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M10 3.5 11.6 8a2 2 0 0 0 1.4 1.4l4.5 1.6-4.5 1.6a2 2 0 0 0-1.4 1.4L10 18.5 8.4 14A2 2 0 0 0 7 12.6L2.5 11 7 9.4A2 2 0 0 0 8.4 8L10 3.5Z" stroke-linejoin="round"/>
            <path d="M18 3v4M16 5h4M18.5 16v3M17 17.5h3" stroke-linecap="round"/>
        </svg>
        @break

    @case('megaphone')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M3.5 10v4a1 1 0 0 0 1 1H7l7.5 4V5L7 9H4.5a1 1 0 0 0-1 1Z" stroke-linejoin="round"/>
            <path d="M7 15l1 5h2.5l-1-4.6M18 9.5a3.5 3.5 0 0 1 0 5M20.5 7a7 7 0 0 1 0 10" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('lightbulb')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M9 17.5h6M10 21h4M12 3a6 6 0 0 0-3.6 10.8c.7.5 1.1 1.3 1.1 2.1v.6h5v-.6c0-.8.4-1.6 1.1-2.1A6 6 0 0 0 12 3Z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('handshake')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="m11 17 2 2a1.4 1.4 0 0 0 2-2m-4 0-2.5-2.5M11 17l-1.5 1.5a1.4 1.4 0 0 1-2-2M15 17l1.5 1.5a1.4 1.4 0 0 0 2-2L13 11l-2 2a1.4 1.4 0 0 1-2-2l3-3h3l3.5 3.5M2.5 12.5 7 8l2 2M21.5 12.5 18 9" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('chart')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="{{ $classes }}">
            <path d="M4 20h16M6.5 16v-4M11 16V8.5M15.5 16v-6M20 16V5" stroke-linecap="round"/>
            <path d="m5 10 5-4.5 4 3 5.5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
@endswitch
