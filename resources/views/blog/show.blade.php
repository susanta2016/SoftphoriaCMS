{{--
    A single blog post. Layout: title block → cover → article with a sticky
    table of contents beside it (lg+) → tags, reactions, share, lead CTA,
    newsletter, discussion, related posts. Structured data (BlogPosting +
    BreadcrumbList) is built in BlogController::show().
--}}
@php
    $initials = fn (?string $name): string => \Illuminate\Support\Str::of($name ?? '?')->explode(' ')->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
    $shareUrl = urlencode($post->url());
    $shareTitle = urlencode($post->title);
@endphp

<x-layouts.site :seo="$seo">
    <x-slot:head>
        <link rel="alternate" type="application/rss+xml" title="{{ $blog->get('title') }}" href="{{ route('blog.feed') }}">
    </x-slot:head>

    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
        @if ($isPreview)
            <div class="fixed inset-x-0 bottom-0 z-40 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950">
                Preview — this post is {{ $post->status->value === 'draft' ? 'a draft' : 'scheduled for '.$post->published_at?->format('M j, Y H:i') }} and not visible to the public.
            </div>
        @endif

        <article>
            {{-- Title block --}}
            <header class="relative isolate overflow-hidden bg-gradient-to-b from-brand-sky to-white pt-28 pb-10 sm:pt-36">
                <div class="pointer-events-none absolute -top-32 left-1/2 -z-10 h-96 w-[48rem] -translate-x-1/2 rounded-full bg-brand-accent/10 blur-3xl" aria-hidden="true"></div>
                <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                    <x-blog.breadcrumbs :items="$breadcrumbs" class="flex justify-center"/>

                    @if ($categoriesOn && $post->category)
                        <a href="{{ $post->category->url() }}" class="mt-8 inline-flex rounded-full bg-white px-4 py-1.5 text-xs font-semibold tracking-wider text-brand-accent uppercase shadow-sm ring-1 ring-brand-accent/15 transition hover:bg-brand-accent hover:text-white">{{ $post->category->name }}</a>
                    @endif

                    <h1 class="mt-5 text-3xl leading-tight font-bold tracking-tight text-brand-navy sm:text-5xl sm:leading-tight">{{ $post->title }}</h1>

                    @if ($post->excerpt)
                        <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-brand-navy/70">{{ $post->excerpt }}</p>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-brand-navy/60">
                        @if ($blog->get('show_author') && $post->author)
                            <span class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-accent text-xs font-bold text-white" aria-hidden="true">{{ $initials($post->author->name) }}</span>
                                <span class="font-semibold text-brand-navy">{{ $post->author->name }}</span>
                            </span>
                            <span aria-hidden="true" class="hidden sm:inline">·</span>
                        @endif
                        <time datetime="{{ $post->published_at?->toAtomString() }}">{{ $post->published_at?->format('F j, Y') }}</time>
                        @if ($blog->get('show_reading_time'))
                            <span aria-hidden="true">·</span>
                            <span>{{ $post->reading_minutes }} min read</span>
                        @endif
                        @if ($post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at->copy()->addDay()))
                            <span aria-hidden="true">·</span>
                            <span>Updated <time datetime="{{ $post->updated_at->toAtomString() }}">{{ $post->updated_at->format('M j, Y') }}</time></span>
                        @endif
                    </div>
                </div>
            </header>

            @if ($coverUrl)
                <div class="mx-auto max-w-5xl px-4 sm:px-6">
                    <img src="{{ $coverUrl }}" alt="{{ $post->cover->alt_text ?: $post->title }}" fetchpriority="high"
                        class="aspect-[16/9] w-full rounded-3xl object-cover shadow-2xl shadow-brand-navy/10">
                </div>
            @endif

            <div class="mx-auto mt-12 grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-12">
                <div @class(['min-w-0', 'lg:col-span-8' => $toc !== [], 'lg:col-span-8 lg:col-start-3' => $toc === []])>
                    @if ($toc !== [])
                        <details class="mb-8 rounded-2xl border border-brand-navy/10 bg-brand-mist p-4 lg:hidden">
                            <summary class="cursor-pointer text-sm font-semibold text-brand-navy">In this article</summary>
                            @include('blog.partials.toc')
                        </details>
                    @endif

                    <div class="blog-prose">
                        {!! $bodyHtml !!}
                    </div>

                    @if ($tagsOn && $post->tags->isNotEmpty())
                        <ul class="mt-10 flex flex-wrap gap-2" aria-label="Tags">
                            @foreach ($post->tags as $tag)
                                <li><a href="{{ $tag->url() }}" class="inline-flex rounded-full bg-brand-sky px-3 py-1 text-sm font-medium text-brand-navy/75 transition hover:bg-brand-accent hover:text-white">#{{ $tag->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-10 flex flex-col gap-6 border-y border-brand-navy/10 py-6 sm:flex-row sm:items-center sm:justify-between">
                        @if ($reactionsOn)
                            @include('blog.partials.reactions')
                        @endif

                        @if ($blog->get('show_share'))
                            <div class="flex items-center gap-2">
                                <span class="mr-1 text-sm font-medium text-brand-navy/60">Share</span>
                                @foreach ([
                                    ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url='.$shareUrl, 'M4.98 3.5a2.5 2.5 0 11-.01 5 2.5 2.5 0 01.01-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05C20.6 8.65 21 11.2 21 14.5V21h-4v-5.7c0-1.36-.02-3.1-1.9-3.1-1.9 0-2.2 1.48-2.2 3v5.8H9z'],
                                    ['X', 'https://twitter.com/intent/tweet?url='.$shareUrl.'&text='.$shareTitle, 'M17.75 3h3.07l-6.7 7.66L22 21h-6.17l-4.83-6.32L5.47 21H2.4l7.17-8.2L2 3h6.33l4.37 5.77zm-1.08 16.2h1.7L7.4 4.73H5.58z'],
                                    ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.$shareUrl, 'M14 8.5V6.8c0-.8.2-1.3 1.4-1.3H17V2.3C16.7 2.3 15.6 2.2 14.4 2.2 11.8 2.2 10 3.8 10 6.7v1.8H7.3v3.3H10V22h4v-10.2h2.8l.4-3.3z'],
                                ] as [$network, $href, $icon])
                                    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer nofollow" aria-label="Share on {{ $network }}"
                                        class="flex h-10 w-10 items-center justify-center rounded-full border border-brand-navy/10 text-brand-navy/70 transition hover:border-brand-accent hover:bg-brand-accent hover:text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                    </a>
                                @endforeach
                                <button type="button" data-copy-link="{{ $post->url() }}" aria-label="Copy link"
                                    class="flex h-10 items-center gap-1.5 rounded-full border border-brand-navy/10 px-3 text-sm font-medium text-brand-navy/70 transition hover:border-brand-accent hover:text-brand-accent">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M10 13a5 5 0 007.07 0l3-3a5 5 0 00-7.07-7.07l-1.5 1.5M14 11a5 5 0 00-7.07 0l-3 3a5 5 0 007.07 7.07l1.5-1.5" stroke-linecap="round"/></svg>
                                    <span data-copy-link-label>Copy link</span>
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Lead capture --}}
                    @if (filled($blog->get('cta_heading')))
                        <aside class="relative mt-12 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-accent to-brand-accent-dark p-8 text-white shadow-xl shadow-brand-accent/20 sm:p-10">
                            <div class="pointer-events-none absolute -right-10 -bottom-16 h-56 w-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                            <h2 class="text-2xl font-bold">{{ $blog->get('cta_heading') }}</h2>
                            @if ($blog->get('cta_text'))
                                <p class="mt-3 max-w-xl leading-relaxed text-white/85">{{ $blog->get('cta_text') }}</p>
                            @endif
                            @if ($blog->get('cta_label') && $blog->get('cta_url'))
                                <a href="{{ $blog->get('cta_url') }}" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-brand-navy shadow-sm transition hover:bg-brand-sky">
                                    {{ $blog->get('cta_label') }} <x-site.arrow class="h-4 w-4"/>
                                </a>
                            @endif
                        </aside>
                    @endif

                    @if ($commentsOn)
                        @include('blog.partials.comments')
                    @endif
                </div>

                @if ($toc !== [])
                    <aside class="hidden lg:col-span-4 lg:block" aria-label="Table of contents">
                        <div class="sticky top-28 rounded-2xl border border-brand-navy/10 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">In this article</p>
                            @include('blog.partials.toc')
                            @if (filled($blog->get('cta_heading')) && $blog->get('cta_url'))
                                <a href="{{ $blog->get('cta_url') }}" class="mt-6 flex items-center justify-between gap-2 rounded-xl bg-brand-sky px-4 py-3 text-sm font-semibold text-brand-accent transition hover:bg-brand-accent hover:text-white">
                                    {{ $blog->get('cta_label') ?: 'Get in touch' }} <x-site.arrow class="h-4 w-4"/>
                                </a>
                            @endif
                        </div>
                    </aside>
                @endif
            </div>
        </article>

        @if ($newsletterOn)
            <div class="mx-auto mt-16 max-w-6xl px-4 sm:px-6">
                <x-blog.newsletter/>
            </div>
        @endif

        @if ($related->isNotEmpty())
            <section class="mt-16 bg-brand-mist py-16" aria-labelledby="related-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="related-heading" class="text-2xl font-bold text-brand-navy">Keep reading</h2>
                        <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-accent hover:text-brand-accent-dark">All articles <x-site.arrow class="h-3.5 w-3.5"/></a>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($related as $relatedPost)
                            <x-blog.card :post="$relatedPost" :style="$blog->get('card_style')" :show-author="$blog->get('show_author')" :show-reading-time="$blog->get('show_reading_time')" :show-category="$categoriesOn"/>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <div class="h-16"></div>
        @endif
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
