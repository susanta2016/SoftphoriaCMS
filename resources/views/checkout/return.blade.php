@php
    $isPaid = $order->status === \App\Modules\Commerce\Enums\OrderStatus::Paid;
@endphp

<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-white pt-28 pb-20">
        <div class="mx-auto max-w-2xl px-4 text-center sm:px-6 lg:px-8">
            @if ($isPaid)
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-gold/10 text-brand-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <h1 class="mt-6 font-serif text-3xl text-brand-navy">Thank you for your purchase</h1>
                <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">
                    We've sent your receipt and download access to <span class="font-semibold text-brand-navy">{{ $order->purchaser_email }}</span>.
                </p>
            @else
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-navy/10 text-brand-navy">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <h1 class="mt-6 font-serif text-3xl text-brand-navy">Confirming your payment</h1>
                <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">
                    This can take a few moments. We'll email <span class="font-semibold text-brand-navy">{{ $order->purchaser_email }}</span> as soon as it's confirmed — no need to wait on this page.
                </p>
            @endif

            <div class="mx-auto mt-8 max-w-md divide-y divide-brand-navy/10 rounded-xl border border-brand-navy/10 text-left">
                @foreach ($order->items as $item)
                    <div class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                        <span class="text-brand-navy">{{ $item->item_title }}</span>
                        <span class="font-medium text-brand-navy">${{ number_format((float) $item->total, 2) }}</span>
                    </div>
                @endforeach
                <div class="flex items-center justify-between gap-4 px-5 py-3 text-sm font-semibold">
                    <span class="text-brand-navy">Total</span>
                    <span class="text-brand-navy">${{ number_format((float) $order->total, 2) }}</span>
                </div>
            </div>

            <a href="{{ route('music.index') }}" class="mt-8 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold hover:text-brand-navy">
                ← Back to Music
            </a>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
