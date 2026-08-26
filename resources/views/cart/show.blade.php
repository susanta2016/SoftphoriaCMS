<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-white pt-28 pb-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h1 class="font-serif text-3xl text-brand-navy">Your Cart</h1>

            @if (session('cart_notice'))
                <p class="mt-4 rounded-md border border-brand-gold/30 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">{{ session('cart_notice') }}</p>
            @endif

            @if (session('cart_error'))
                <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('cart_error') }}</p>
            @endif

            @if ($lines->isEmpty())
                <div class="mt-10 rounded-xl border border-brand-navy/10 p-10 text-center">
                    <p class="text-sm text-brand-navy/60">Your cart is empty.</p>
                    <a href="{{ route('music.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold hover:text-brand-navy">
                        Browse Music <span aria-hidden="true">→</span>
                    </a>
                </div>
            @else
                <div class="mt-8 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                    @foreach ($lines as $line)
                        <div class="flex items-center gap-4 py-5">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-brand-navy/10">
                                @if ($line['coverUrl'])
                                    <img src="{{ $line['coverUrl'] }}" alt="{{ $line['title'] }}" class="h-full w-full object-cover">
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <a href="{{ $line['showRoute'] }}" class="font-serif text-lg text-brand-navy hover:text-brand-gold">{{ $line['title'] }}</a>
                                <p class="text-xs font-semibold tracking-wide text-brand-gold uppercase">Digital {{ ucfirst($line['type']) }}</p>
                            </div>

                            <p class="shrink-0 font-medium text-brand-navy">${{ number_format($line['price'], 2) }}</p>

                            <form method="POST" action="{{ route('cart.remove') }}" class="shrink-0">
                                @csrf
                                <input type="hidden" name="type" value="{{ $line['type'] }}">
                                <input type="hidden" name="id" value="{{ $line['model']->id }}">
                                <button type="submit" class="text-sm font-medium text-brand-navy/50 underline decoration-brand-navy/30 underline-offset-2 hover:text-red-600">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <span class="text-sm font-semibold tracking-wide text-brand-navy/60 uppercase">Subtotal</span>
                    <span class="font-serif text-2xl text-brand-navy">${{ number_format($subtotal, 2) }}</span>
                </div>

                <a href="{{ route('checkout.show') }}" class="mt-6 flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3.5 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light">
                    Proceed to Checkout
                </a>

                <a href="{{ route('music.index') }}" class="mt-4 block text-center text-sm font-medium text-brand-navy/60 hover:text-brand-gold">
                    ← Continue browsing
                </a>
            @endif
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
