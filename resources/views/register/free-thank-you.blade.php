<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="mx-auto max-w-xl px-4 pt-32 pb-24 text-center sm:px-6 lg:px-8">
        <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-gold/10 text-brand-gold">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7">
                <path d="M3 8l9 6 9-6M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>

        <h1 class="mt-6 font-serif text-3xl text-brand-navy sm:text-4xl">Thank You</h1>

        <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
            <span class="h-px w-12 bg-brand-gold/70"></span>
            <span class="text-brand-gold">✦</span>
            <span class="h-px w-12 bg-brand-gold/70"></span>
        </div>

        <p class="text-base leading-relaxed text-brand-navy/80">{{ $message }}</p>

        <div class="mt-8 text-xs text-brand-navy/60">
            <p class="mb-2">Didn't get the email?</p>
            <form method="POST" action="{{ route('verification.resend') }}" class="flex items-center justify-center gap-2">
                @csrf
                <input
                    type="email" name="email" placeholder="Your email address" required
                    class="rounded-md border border-brand-navy/20 px-3 py-1.5 text-xs text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                >
                <button type="submit" class="rounded-md border border-brand-navy/20 px-3 py-1.5 font-semibold text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                    Resend
                </button>
            </form>
        </div>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
