{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ $description }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
        @if ($posts->isNotEmpty())
            <lastBuildDate>{{ $posts->first()->published_at->toRssString() }}</lastBuildDate>
        @endif
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $post->url() }}</link>
                <guid isPermaLink="true">{{ $post->url() }}</guid>
                <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
                @if ($post->author)
                    <dc:creator>{{ $post->author->name }}</dc:creator>
                @endif
                @if ($post->category)
                    <category>{{ $post->category->name }}</category>
                @endif
                <description>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 300) }}</description>
            </item>
        @endforeach
    </channel>
</rss>
