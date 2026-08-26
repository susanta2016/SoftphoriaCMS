<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-white pt-28 pb-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h1 class="font-serif text-3xl text-brand-navy">Checkout</h1>
            <p class="mt-2 text-sm text-brand-navy/60">Digital delivery only — nothing to ship, and your download access follows by email.</p>

            @if (session('cart_error'))
                <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('cart_error') }}</p>
            @endif

            <div class="mt-8 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                @foreach ($lines as $line)
                    <div class="flex items-center justify-between gap-4 py-4 text-sm">
                        <div>
                            <span class="font-medium text-brand-navy">{{ $line['title'] }}</span>
                            <span class="ml-2 text-xs font-semibold tracking-wide text-brand-gold uppercase">Digital {{ ucfirst($line['type']) }}</span>
                        </div>
                        <span class="font-medium text-brand-navy">${{ number_format($line['price'], 2) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between">
                <span class="text-sm font-semibold tracking-wide text-brand-navy/60 uppercase">Total</span>
                <span class="font-serif text-2xl text-brand-navy">${{ number_format($subtotal, 2) }}</span>
            </div>

            <form method="POST" action="{{ route('checkout.process') }}" class="mt-8">
                @csrf

                @auth
                    <p class="rounded-md border border-brand-navy/10 bg-white px-4 py-3 text-sm text-brand-navy/75">
                        Purchasing as <span class="font-semibold text-brand-navy">{{ auth()->user()->name }}</span> ({{ auth()->user()->email }}).
                    </p>
                @else
                    <div class="rounded-xl border border-brand-navy/10 p-5">
                        <h2 class="font-serif text-lg text-brand-navy">Your details</h2>
                        <p class="mt-1 text-xs text-brand-navy/60">We'll email your download access here — no account required.</p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="checkout-name" class="mb-1 block text-xs font-semibold tracking-wide text-brand-navy/60 uppercase">Name</label>
                                <input id="checkout-name" type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="checkout-email" class="mb-1 block text-xs font-semibold tracking-wide text-brand-navy/60 uppercase">Email</label>
                                <input id="checkout-email" type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="checkout-phone" class="mb-1 block text-xs font-semibold tracking-wide text-brand-navy/60 uppercase">Contact Number</label>
                                <input id="checkout-phone" type="tel" name="phone" value="{{ old('phone') }}" required class="w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                                @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <p class="mt-4 text-xs text-brand-navy/50">
                            Prefer an account? <a href="{{ route('register.show') }}" class="font-semibold text-brand-gold hover:text-brand-navy">Register</a> — but it's not required to buy.
                        </p>
                    </div>
                @endauth

                <button type="submit" class="mt-6 flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3.5 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light">
                    Continue to Payment
                </button>
            </form>

            <a href="{{ route('cart.show') }}" class="mt-4 block text-center text-sm font-medium text-brand-navy/60 hover:text-brand-gold">
                ← Back to Cart
            </a>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
