<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-brand-ivory">
        <main class="mx-auto max-w-6xl px-4 pt-32 pb-20 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Poetry &amp; Prose</h1>
                <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                </div>
                <p class="mx-auto max-w-xl text-sm text-brand-navy/70">Essays, reflections, hymns, poetry, and articles.</p>
            </div>

            <form method="GET" action="{{ route('poetry-prose.index') }}" class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <select name="content_type" onchange="this.form.submit()" class="rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                    <option value="">All Types</option>
                    @foreach ($contentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('content_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                @if ($collections->isNotEmpty())
                    <select name="collection" onchange="this.form.submit()" class="rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                        <option value="">All Collections</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection->slug }}" @selected(request('collection') === $collection->slug)>{{ $collection->title }}</option>
                        @endforeach
                    </select>
                @endif

                @if (request('content_type') || request('collection'))
                    <a href="{{ route('poetry-prose.index') }}" class="text-sm font-medium text-brand-navy/60 underline hover:text-brand-gold">Clear filters</a>
                @endif
            </form>

            @if ($entries->isEmpty())
                <p class="mt-16 text-center text-sm text-brand-navy/60">No entries to show yet.</p>
            @else
                <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($entries as $entry)
                        <a href="{{ route('poetry-prose.show', $entry) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-brand-navy/5 transition hover:ring-brand-gold/40">
                            @if ($entry->featuredImage)
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::disk($entry->featuredImage->disk)->url($entry->featuredImage->path) }}"
                                    alt="{{ $entry->title }}"
                                    class="h-44 w-full object-cover"
                                >
                            @endif
                            <div class="flex flex-1 flex-col p-5">
                                <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $entry->content_type->getLabel() }}</span>
                                <h2 class="mt-2 font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $entry->title }}</h2>
                                @if ($entry->author)
                                    <p class="mt-2 text-xs text-brand-navy/60">By {{ $entry->author->name }}</p>
                                @endif
                                @if ($entry->publish_at)
                                    <p class="mt-auto pt-4 text-xs text-brand-navy/50">{{ $entry->publish_at->format('M j, Y') }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $entries->onEachSide(1)->links() }}
                </div>
            @endif
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
