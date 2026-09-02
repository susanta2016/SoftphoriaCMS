@php
    $shareUrl = route('inspirational-resources.show', $submission);
@endphp

<x-layouts.site :seo="$seo">
    <div class="relative overflow-hidden bg-brand-ivory pt-28 pb-10 sm:pt-32">
        <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Inspirational Resources', 'url' => route('inspirational-resources.index')],
                ['label' => $submission->publicTitle()],
            ]"/>

            <p class="mt-4 text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $submission->category }}</p>
            <h1 class="mt-2 font-serif text-3xl text-brand-navy sm:text-4xl">{{ $submission->publicTitle() }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-brand-navy/70">{{ $submission->excerpt(220) }}</p>
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-4 text-sm text-brand-navy/60">
                            <span class="inline-flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4" stroke-linecap="round"/></svg>
                                {{ $submission->created_at->format('F j, Y') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="8" r="3"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6" stroke-linecap="round"/></svg>
                                Shared by {{ $submission->name }}
                            </span>
                        </div>

                        <div class="inline-flex items-center gap-2">
                            <span class="text-xs font-medium text-brand-navy/50">Share:</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($submission->publicTitle()) }}" target="_blank" rel="noopener" aria-label="Share on X" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.8l-4.7-6.2L5 21H3l7.1-8-8-10h6.9l4.3 5.7L18.9 3Z"/></svg>
                            </a>
                        </div>
                    </div>

                    <div class="mt-8 text-base leading-relaxed whitespace-pre-line text-brand-navy/80">{{ $submission->message }}</div>

                    @if ($submission->reference_url)
                        <div class="mt-6 rounded-2xl bg-brand-ivory p-5 ring-1 ring-brand-navy/5">
                            <span class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">Reference</span>
                            <a href="{{ $submission->reference_url }}" target="_blank" rel="noopener" class="mt-1 block truncate font-serif text-lg text-brand-navy transition hover:text-brand-gold">
                                {{ $submission->reference_url }}
                            </a>
                        </div>
                    @endif

                    @if ($previous || $next)
                        <div class="mt-12 grid grid-cols-1 gap-4 border-t border-brand-navy/10 pt-8 sm:grid-cols-2">
                            @if ($previous)
                                <a href="{{ route('inspirational-resources.show', $previous) }}" class="group rounded-md border border-brand-navy/15 px-5 py-4 transition hover:border-brand-gold">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-navy/50 uppercase"><span aria-hidden="true">←</span> Previous</span>
                                    <span class="mt-1 block font-serif text-brand-navy transition group-hover:text-brand-gold">{{ $previous->publicTitle() }}</span>
                                </a>
                            @else
                                <div></div>
                            @endif

                            @if ($next)
                                <a href="{{ route('inspirational-resources.show', $next) }}" class="group rounded-md border border-brand-navy/15 px-5 py-4 text-right transition hover:border-brand-gold">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-navy/50 uppercase">Next <span aria-hidden="true">→</span></span>
                                    <span class="mt-1 block font-serif text-brand-navy transition group-hover:text-brand-gold">{{ $next->publicTitle() }}</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-28">
                        @include('inspirational-resources.partials.sidebar-about')
                        @include('inspirational-resources.partials.sidebar-categories')
                        @include('inspirational-resources.partials.sidebar-recent')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
