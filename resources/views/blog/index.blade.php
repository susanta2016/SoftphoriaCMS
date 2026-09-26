{{-- Blog landing page, category and tag archives, and search results. --}}
<x-layouts.site :seo="$seo">
    <x-slot:head>
        <link rel="alternate" type="application/rss+xml" title="{{ $blog->get('title') }}" href="{{ route('blog.feed') }}">
        @if ($posts->previousPageUrl())
            <link rel="prev" href="{{ $posts->previousPageUrl() }}">
        @endif
        @if ($posts->nextPageUrl())
            <link rel="next" href="{{ $posts->nextPageUrl() }}">
        @endif
    </x-slot:head>

    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
        @include('blog.partials.page')
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
