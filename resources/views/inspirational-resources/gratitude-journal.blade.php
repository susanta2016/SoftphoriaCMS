<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-brand-ivory pt-28 pb-10 sm:pt-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-xl">
                <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">Inspirational Resources</span>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">Gratitude Journal</h1>
                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                </div>
                <p class="max-w-xl text-base leading-relaxed text-brand-navy/75">
                    Private gratitude shared within our member community.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white py-14 sm:py-16">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if ($entries->isEmpty())
                <p class="mt-10 text-center text-sm text-brand-navy/60">No Gratitude Journal entries yet — check back soon.</p>
            @else
                <p class="text-xs font-semibold tracking-[0.15em] text-brand-gold uppercase">
                    {{ $entries->total() }} {{ $entries->total() === 1 ? 'Reflection' : 'Reflections' }} Shared Within Our Member Community
                </p>

                <ul class="mt-6 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                    @foreach ($entries as $entry)
                        <li class="py-8 sm:py-9">
                            <p class="max-w-3xl font-serif text-xl leading-relaxed text-brand-navy sm:text-2xl">{{ $entry->content }}</p>
                            <p class="mt-4 text-xs text-brand-navy/50">
                                {{ $entry->user?->name ?? 'A member' }} · <span class="tabular-nums">{{ $entry->created_at?->format('M j, Y') }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>

                @if ($entries->hasPages())
                    <div class="mt-10 border-t border-brand-navy/10 pt-8">
                        {{ $entries->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
