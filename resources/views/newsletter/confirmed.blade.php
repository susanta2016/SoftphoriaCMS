<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="mx-auto max-w-xl px-4 pt-32 pb-24 text-center sm:px-6 lg:px-8">
        @if ($confirmed)
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-gold/10 text-brand-gold">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7">
                    <path d="m4.5 12.75 6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>

            <h1 class="mt-6 font-serif text-3xl text-brand-navy sm:text-4xl">Subscription Confirmed</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-navy/80">You're all set — you'll now receive our newsletter.</p>
        @else
            <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Link Invalid or Expired</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-navy/80">
                This confirmation link is invalid, has already been used, or has expired. Submit your email through the newsletter signup form again to receive a new link.
            </p>

            <a href="{{ route('home') }}" class="mt-6 inline-block rounded-md bg-brand-gold px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-gold-light">
                Back to Home
            </a>
        @endif
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
