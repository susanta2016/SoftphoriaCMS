<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-white pt-28 pb-20">
        <div class="mx-auto max-w-md px-4 text-center sm:px-6 lg:px-8">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-gold/10 text-brand-gold">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7">
                    <rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/>
                </svg>
            </span>
            <h1 class="mt-6 font-serif text-3xl text-brand-navy">Verify Your Purchase</h1>
            <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">
                Enter the email address you used when purchasing this order. We'll use it to confirm it's you before showing your downloads.
            </p>

            @if (session('guest_verify_error'))
                <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-left text-sm text-red-700">{{ session('guest_verify_error') }}</p>
            @endif

            <form method="POST" action="{{ route('downloads.guest.verify', $order) }}" class="mt-8 text-left">
                @csrf

                <label for="guest-verify-email" class="mb-1 block text-xs font-semibold tracking-wide text-brand-navy/60 uppercase">Purchase Email</label>
                <input
                    id="guest-verify-email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                >
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                <button type="submit" class="mt-6 flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3.5 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light">
                    Verify &amp; Continue
                </button>
            </form>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
