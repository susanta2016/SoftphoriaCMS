@php
    use App\Modules\Commerce\Enums\DownloadAccessType;
@endphp

<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Downloads</h1>
                <p class="mt-1 text-sm text-brand-navy/70">Your track download history.</p>
            </div>
            <span class="rounded-full bg-brand-gold/10 px-4 py-1.5 text-sm font-semibold text-brand-navy">
                {{ $totalDownloads }} download{{ $totalDownloads === 1 ? '' : 's' }}
            </span>
        </div>

        @if ($downloads->isEmpty())
            <p class="mt-6 text-sm text-brand-navy/60">You haven't downloaded any tracks yet.</p>
        @else
            <div class="mt-6 divide-y divide-brand-navy/5 border-t border-brand-navy/5">
                @foreach ($downloads as $download)
                    @php
                        $track = $download->track;
                        $release = $track?->album ?? $track?->single;
                    @endphp

                    <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0">
                            @if ($track)
                                <a href="{{ route('music.tracks.show', $track) }}" class="truncate text-sm font-medium text-brand-navy hover:text-brand-gold">{{ $track->title }}</a>
                            @else
                                <span class="text-sm font-medium text-brand-navy/50">Track no longer available</span>
                            @endif
                            @if ($release)
                                <p class="mt-0.5 truncate text-xs text-brand-navy/50">{{ $release->title }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-xs text-brand-navy/60">
                            <span class="rounded-full border border-brand-navy/15 px-2.5 py-1 font-semibold tracking-wide uppercase">
                                {{ $download->access_type === DownloadAccessType::Membership ? 'Pro Membership' : 'Purchase' }}
                            </span>
                            <span class="tabular-nums">{{ $download->created_at?->format('M j, Y g:i A') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $downloads->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</x-layouts.account>
