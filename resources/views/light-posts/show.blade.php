@php
    use App\Models\User;

    $name = $lightPost->user?->name ?: 'A Member';
@endphp

<x-layouts.site :seo="$seo">
    <div class="relative overflow-hidden bg-brand-ivory pt-28 pb-10 sm:pt-32">
        <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'A Little Light'],
            ]"/>

            {{-- Eyebrow + heading (client feedback: a Light Post's own page
                should make it immediately obvious what you're reading,
                rather than looking like a bare, unlabeled text page). Same
                eyebrow pattern the site's other content pages already use
                (e.g. inspirational-resources/gratitude-journal.blade.php). --}}
            <span class="mt-4 block text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">A Little Light ✨</span>
            <h1 class="mt-2 font-serif text-2xl text-brand-navy sm:text-3xl">A Light Shared by {{ $name }}</h1>
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-brand-navy/10 p-8 sm:p-10">
                <div class="flex items-center gap-3">
                    <img src="{{ $lightPost->user?->avatarUrl() ?? User::defaultAvatarUrl() }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-brand-navy">{{ $name }}</p>
                        <p class="text-xs text-brand-navy/50">{{ $lightPost->created_at->format('F j, Y') }}</p>
                    </div>
                </div>

                <p class="mt-8 text-xl leading-relaxed whitespace-pre-line text-brand-navy/85">&ldquo;{{ $lightPost->content }}&rdquo;</p>
            </div>

            <div class="mt-6">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy">
                    <span aria-hidden="true">←</span> Back to Home
                </a>
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
