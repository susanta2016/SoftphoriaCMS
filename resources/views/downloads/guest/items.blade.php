@php
    use App\Modules\Commerce\Enums\EntitlementStatus;
@endphp

<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-white pt-28 pb-20">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 rounded-md border border-brand-gold/30 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 shrink-0 text-brand-gold"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Your purchase is verified.</span>
            </div>

            <h1 class="mt-6 font-serif text-3xl text-brand-navy">Your Downloads</h1>
            <p class="mt-2 text-sm text-brand-navy/60">Order total: <span class="font-semibold text-brand-navy">${{ number_format((float) $order->total, 2) }}</span></p>

            @if (session('download_error'))
                <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('download_error') }}</p>
            @endif

            <div class="mt-8 space-y-6">
                @foreach ($order->items as $item)
                    @php
                        // Decided per line item: a full-album purchase is
                        // ZIP-only (no per-track links); an individually
                        // purchased song (Single, or an Album-owned 'track'
                        // item) exposes exactly that one song — never its
                        // parent Album's other tracks.
                        $isAlbumPurchase = $item->album_id !== null;
                        $tracks = match (true) {
                            $isAlbumPurchase => collect(),
                            $item->single_id !== null => collect([$item->single->track])->filter(),
                            default => collect([$item->track])->filter(),
                        };
                        $entitlement = $item->entitlement;
                        $entitlementActive = $entitlement && $entitlement->status() === EntitlementStatus::Active;
                    @endphp

                    <div class="rounded-2xl border border-brand-navy/10 p-5">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="font-serif text-lg text-brand-navy">{{ $item->item_title }}</h2>
                            @if ($entitlement && $entitlement->remainingDownloads() !== null)
                                <span class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">
                                    {{ $entitlement->remainingDownloads() }} download{{ $entitlement->remainingDownloads() === 1 ? '' : 's' }} left
                                </span>
                            @endif
                        </div>

                        @if ($entitlement?->expires_at)
                            <p class="mt-1 text-xs text-brand-navy/50">Access until {{ $entitlement->expires_at->format('M j, Y') }}</p>
                        @endif

                        @if ($isAlbumPurchase && $entitlement)
                            <div id="album-zip" class="mt-4 flex items-center justify-between gap-4 rounded-md bg-brand-navy/5 px-4 py-3 text-sm">
                                <span class="text-brand-navy">Full album (ZIP)</span>

                                @if ($entitlementActive)
                                    <a href="{{ route('downloads.guest.album-zip', [$order, $entitlement]) }}" class="inline-flex items-center gap-2 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-4 py-2 text-xs font-semibold tracking-wide text-brand-navy uppercase transition hover:bg-brand-gold/20">
                                        Download ZIP
                                    </a>
                                @else
                                    <span class="text-xs font-medium text-brand-navy/40 uppercase">Unavailable</span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-4 divide-y divide-brand-navy/5">
                            @foreach ($tracks as $track)
                                @php
                                    $available = $entitlementActive && $track->audio_media_id !== null;
                                @endphp
                                <div class="flex items-center justify-between gap-4 py-3 text-sm">
                                    <span class="text-brand-navy">{{ $track->title }}</span>

                                    @if ($available)
                                        <a href="{{ route('downloads.guest.track', [$order, $track]) }}" class="inline-flex items-center gap-2 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-4 py-2 text-xs font-semibold tracking-wide text-brand-navy uppercase transition hover:bg-brand-gold/20">
                                            Download
                                        </a>
                                    @else
                                        <span class="text-xs font-medium text-brand-navy/40 uppercase">Unavailable</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
