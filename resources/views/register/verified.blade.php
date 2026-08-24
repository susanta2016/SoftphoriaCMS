<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="mx-auto max-w-xl px-4 pt-32 pb-24 text-center sm:px-6 lg:px-8">
        @if ($verified)
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-gold/10 text-brand-gold">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7">
                    <path d="m4.5 12.75 6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>

            <h1 class="mt-6 font-serif text-3xl text-brand-navy sm:text-4xl">Email Verified</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-navy/80">Your email address has been verified and your account is now active.</p>
        @else
            <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Link Invalid or Expired</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-navy/80">
                This verification link is invalid, has already been used, or has expired. You can request a new one below.
            </p>

            <form method="POST" action="{{ route('verification.resend') }}" class="mt-6 flex items-center justify-center gap-2">
                @csrf
                <input
                    type="email" name="email" placeholder="Your email address" required
                    class="rounded-md border border-brand-navy/20 px-3.5 py-2 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                >
                <button type="submit" class="rounded-md bg-brand-gold px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-gold-light">
                    Resend
                </button>
            </form>
        @endif
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
