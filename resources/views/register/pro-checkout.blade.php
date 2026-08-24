<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="mx-auto max-w-xl px-4 pt-32 pb-24 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Complete Your Payment</h1>
            <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                <span class="h-px w-12 bg-brand-gold/70"></span>
                <span class="text-brand-gold">✦</span>
                <span class="h-px w-12 bg-brand-gold/70"></span>
            </div>
            <p class="text-sm text-brand-navy/70">Enter your card details below to activate your Pro Membership. Your card details are entered directly with Stripe and never reach our servers.</p>
        </div>

        <div class="mt-8 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-brand-navy/5 sm:p-6">
            @if ($stripeKey === '')
                <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Payment is not configured in this environment (no Stripe API key set), so the embedded card form cannot be displayed here.
                </div>
            @else
                <div id="checkout"></div>
                <script src="https://js.stripe.com/v3/"></script>
                <script>
                    (function () {
                        var stripe = Stripe({{ \Illuminate\Support\Js::from($stripeKey) }});

                        stripe.initEmbeddedCheckout({
                            clientSecret: {{ \Illuminate\Support\Js::from($clientSecret) }},
                        }).then(function (checkout) {
                            checkout.mount('#checkout');
                        });
                    })();
                </script>
            @endif
        </div>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
