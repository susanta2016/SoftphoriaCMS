<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-brand-ivory">
        <main class="mx-auto max-w-3xl px-4 pt-32 pb-20 sm:px-6 lg:px-8">
            <a href="{{ route('poetry-prose.index') }}" class="text-sm font-medium text-brand-navy/60 transition hover:text-brand-gold">← Back to Poetry &amp; Prose</a>

            <article class="mt-6 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-10">
                <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $entry->content_type->getLabel() }}</span>
                <h1 class="mt-2 font-serif text-3xl text-brand-navy sm:text-4xl">{{ $entry->title }}</h1>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-brand-navy/60">
                    @if ($entry->author)
                        <span>By {{ $entry->author->name }}</span>
                    @endif
                    @if ($entry->publish_at)
                        <span>{{ $entry->publish_at->format('F j, Y') }}</span>
                    @endif
                </div>

                @if ($entry->featuredImage)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk($entry->featuredImage->disk)->url($entry->featuredImage->path) }}"
                        alt="{{ $entry->title }}"
                        class="mt-6 w-full rounded-xl object-cover"
                    >
                @endif

                <div class="prose prose-neutral mt-8 max-w-none">
                    {!! $entry->body !!}
                </div>

                @if ($entry->categories->isNotEmpty() || $entry->tags->isNotEmpty())
                    <div class="mt-8 flex flex-wrap gap-2 border-t border-brand-navy/10 pt-6">
                        @foreach ($entry->categories as $category)
                            <span class="rounded-full bg-brand-navy/10 px-3 py-1 text-xs text-brand-navy">{{ $category->name }}</span>
                        @endforeach
                        @foreach ($entry->tags as $tag)
                            <span class="rounded-full bg-brand-gold/10 px-3 py-1 text-xs text-brand-gold">#{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </article>

            @if ($entry->collection)
                <div class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5">
                    <h2 class="font-serif text-lg text-brand-navy">Part of</h2>
                    <a href="{{ route('poetry-prose.index', ['collection' => $entry->collection->slug]) }}" class="mt-2 inline-block text-sm font-medium text-brand-gold transition hover:text-brand-gold-light">
                        {{ $entry->collection->title }}
                    </a>
                </div>
            @endif
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
