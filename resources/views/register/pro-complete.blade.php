@php
    // Capped so a genuinely stuck webhook doesn't refresh this page forever
    // — ~20 attempts at 3s apart is a full minute of grace before we stop
    // and ask the visitor to check back themselves.
    $maxAttempts = 20;
    $stillWaiting = ! $confirmed && $attempt < $maxAttempts;
@endphp
<x-layouts.site :seo="$seo">
    @if ($stillWaiting)
        <meta http-equiv="refresh" content="3;url={{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['attempt' => $attempt + 1])) }}">
    @endif

    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="mx-auto max-w-xl px-4 pt-32 pb-24 text-center sm:px-6 lg:px-8">
        @if ($confirmed)
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
        @elseif ($stillWaiting)
            <span class="inline-flex h-14 w-14 animate-spin items-center justify-center rounded-full border-2 border-brand-gold/30 border-t-brand-gold"></span>

            <h1 class="mt-6 font-serif text-3xl text-brand-navy sm:text-4xl">Confirming Your Payment</h1>
            <p class="mt-4 text-sm text-brand-navy/70">This usually only takes a moment — this page will refresh automatically.</p>
        @else
            <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Still Confirming</h1>
            <p class="mt-4 text-sm text-brand-navy/70">
                We're still waiting to hear back from Stripe about your payment. This can occasionally take a little longer than expected —
                please check back in a few minutes, or contact us if this persists.
            </p>
        @endif
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
