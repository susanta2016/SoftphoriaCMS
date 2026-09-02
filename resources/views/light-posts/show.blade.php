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
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-brand-navy/10 p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <img src="{{ $lightPost->user?->avatarUrl() ?? User::defaultAvatarUrl() }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-brand-navy">{{ $name }}</p>
                        <p class="text-xs text-brand-navy/50">{{ $lightPost->created_at->format('F j, Y') }}</p>
                    </div>
                </div>

                <p class="mt-6 text-lg leading-relaxed whitespace-pre-line text-brand-navy/85">&ldquo;{{ $lightPost->content }}&rdquo;</p>
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
