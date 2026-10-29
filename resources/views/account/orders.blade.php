@php
    use App\Modules\Commerce\Enums\EntitlementStatus;
@endphp

<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Orders</h1>
        <p class="mt-1 text-sm text-brand-navy/70">Your digital music purchases and downloads.</p>

        @if (session('download_error'))
            <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('download_error') }}</p>
        @endif

        @if ($orders->isEmpty())
            <p class="mt-6 text-sm text-brand-navy/60">You don't have any purchases yet.</p>
        @else
            <div class="mt-6 space-y-6">
                @foreach ($orders as $order)
                    <div class="rounded-2xl border border-brand-navy/10 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">Order {{ $order->public_id }}</span>
                                <p class="text-sm text-brand-navy/70">{{ $order->paid_at?->format('M j, Y') }}</p>
                            </div>
                            <span class="font-serif text-lg text-brand-navy">${{ number_format((float) $order->total, 2) }}</span>
                        </div>

                        <div class="mt-4 divide-y divide-brand-navy/5 border-t border-brand-navy/5">
                            @foreach ($order->items as $item)
                                @php
                                    // Decided per line item: a full-album purchase is
                                    // ZIP-only (no per-track links); an individually
                                    // purchased song (Single, or an Album-owned 'track'
                                    // item) lists exactly that one song.
                                    $isAlbumPurchase = $item->album_id !== null;
                                    $tracks = match (true) {
                                        $isAlbumPurchase => collect(),
                                        $item->single_id !== null => collect([$item->single?->track])->filter(),
                                        default => collect([$item->track])->filter(),
                                    };
                                    $entitlement = $item->entitlement;
                                    $entitlementActive = $entitlement && $entitlement->status() === EntitlementStatus::Active;
                                @endphp

                                <div class="py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-sm font-medium text-brand-navy">{{ $item->item_title }}</span>
                                        @if ($entitlement && $entitlement->remainingDownloads() !== null)
                                            <span class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">
                                                {{ $entitlement->remainingDownloads() }} download{{ $entitlement->remainingDownloads() === 1 ? '' : 's' }} left
                                            </span>
                                        @endif
                                    </div>

                                    @if ($entitlement?->expires_at)
                                        <p class="mt-0.5 text-xs text-brand-navy/50">Access until {{ $entitlement->expires_at->format('M j, Y') }}</p>
                                    @endif

                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($tracks as $track)
                                            @php
                                                $available = $entitlementActive && $track->audio_media_id !== null;
                                            @endphp
                                            <div class="flex items-center justify-between gap-4 text-sm text-brand-navy/70">
                                                <span>{{ $track->title }}</span>
                                                @if ($available)
                                                    <a href="{{ route('music.tracks.download', $track) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-brand-gold uppercase hover:text-brand-navy">
                                                        Download
                                                    </a>
                                                @else
                                                    <span class="text-xs font-medium text-brand-navy/40 uppercase">Unavailable</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @if ($isAlbumPurchase && $entitlement)
                                        <div id="album-zip" class="mt-2 flex items-center justify-between gap-4 rounded-md bg-brand-navy/5 px-3 py-2 text-sm text-brand-navy">
                                            <span>Full album (ZIP)</span>
                                            @if ($entitlementActive)
                                                <a href="{{ route('account.orders.album-zip', $entitlement) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-brand-gold uppercase hover:text-brand-navy">
                                                    Download ZIP
                                                </a>
                                            @else
                                                <span class="text-xs font-medium text-brand-navy/40 uppercase">Unavailable</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $orders->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</x-layouts.account>
