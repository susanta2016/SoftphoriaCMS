@props(['siteName' => 'All The Things Light', 'tagline' => null])

@php
    $explore = [
        'About' => '#',
        'Music' => '#',
        'Podcast' => '#',
        'Poetry/Prose' => '#',
        'Inspirational Resources' => '#',
        'Contact' => '#',
    ];
    $community = [
        'Latest Comments' => '#',
        'Join Our Community' => '#',
    ];
    $support = [
        'Privacy Policy' => '#',
        'Terms of Use' => '#',
        'Cookie Policy' => '#',
        'Contact Us' => '#',
    ];
    $social = [
        'Facebook' => ['href' => '#', 'path' => 'M13.5 9H15V6.5h-1.5C11.6 6.5 10 8.1 10 10.1V12H8v2.5h2V21h2.5v-6.5H15l.5-2.5h-3v-1.4c0-.6.4-1.1 1-1.1Z'],
        'Instagram' => ['href' => '#', 'path' => 'M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm0 1.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm4.75-3.5a.9.9 0 1 0 0 1.8.9.9 0 0 0 0-1.8ZM8 4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8a4 4 0 0 1 4-4Zm0 1.5A2.5 2.5 0 0 0 5.5 8v8A2.5 2.5 0 0 0 8 18.5h8a2.5 2.5 0 0 0 2.5-2.5V8A2.5 2.5 0 0 0 16 5.5H8Z'],
        'YouTube' => ['href' => '#', 'path' => 'M21.6 8.2a2.5 2.5 0 0 0-1.8-1.8C18.1 6 12 6 12 6s-6.1 0-7.8.4A2.5 2.5 0 0 0 2.4 8.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 3.8 2.5 2.5 0 0 0 1.8 1.8C6.1 18 12 18 12 18s6.1 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-3.8ZM10 15V9l5 3-5 3Z'],
        'Twitter' => ['href' => '#', 'path' => 'M18.9 6.1a5.4 5.4 0 0 1-1.6.5 2.8 2.8 0 0 0 1.2-1.6c-.6.3-1.2.6-1.9.7a2.8 2.8 0 0 0-4.8 2.6A8 8 0 0 1 5.9 5.8a2.8 2.8 0 0 0 .9 3.8 2.8 2.8 0 0 1-1.3-.4v.1a2.8 2.8 0 0 0 2.3 2.8 2.8 2.8 0 0 1-1.3.1 2.8 2.8 0 0 0 2.6 2 5.7 5.7 0 0 1-4.2 1.2 8 8 0 0 0 4.4 1.3c5.2 0 8.1-4.4 8.1-8.2v-.4a5.9 5.9 0 0 0 1.5-1.5Z'],
    ];
@endphp

<footer class="relative isolate overflow-hidden bg-brand-sky">
    {{-- Decorative watermark ring behind the Community column, matching the approved footer artwork. --}}
    <div
        aria-hidden="true"
        class="pointer-events-none absolute top-1/2 left-1/2 -z-10 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full border-4 border-brand-gold/25 sm:h-80 sm:w-80"
    ></div>

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-x-8 gap-y-10 sm:grid-cols-3 lg:grid-cols-5">
            <div class="col-span-2 sm:col-span-3 lg:col-span-1">
                <x-site.brand-mark :site-name="$siteName" :tagline="$tagline" :on-dark="false"/>
                <p class="mt-4 max-w-xs text-sm text-brand-navy/70">
                    A creative home for music, writing, reflection, thinking, and community.
                </p>
                <div class="mt-5 flex gap-2">
                    @foreach ($social as $name => $item)
                        <a href="{{ $item['href'] }}" aria-label="{{ $name }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-navy text-white transition hover:bg-brand-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="{{ $item['path'] }}"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Explore <span class="text-brand-gold" aria-hidden="true">✦</span></h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($explore as $label => $href)
                        <li><a href="{{ $href }}" class="text-sm text-brand-navy/75 transition hover:text-brand-gold">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Community <span class="text-brand-gold" aria-hidden="true">✦</span></h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($community as $label => $href)
                        <li><a href="{{ $href }}" class="text-sm text-brand-navy/75 transition hover:text-brand-gold">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Support <span class="text-brand-gold" aria-hidden="true">✦</span></h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($support as $label => $href)
                        <li><a href="{{ $href }}" class="text-sm text-brand-navy/75 transition hover:text-brand-gold">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

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
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-brand-navy/15 pt-6 sm:flex-row">
            <p class="text-xs text-brand-navy/60">&copy; {{ now()->year }} {{ $siteName }}. All rights reserved.</p>
        </div>
    </div>
</footer>
