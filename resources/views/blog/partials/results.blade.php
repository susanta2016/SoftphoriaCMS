{{-- The post grid + pagination of a blog listing (inside blog/partials/page). --}}
<div aria-live="polite">
    @if ($search !== '')
        <p class="mb-6 text-sm text-brand-navy/65">
            {{ $posts->total() }} {{ \Illuminate\Support\Str::plural('result', $posts->total()) }} for “<span class="font-semibold text-brand-navy">{{ $search }}</span>”
            · <a href="{{ route('blog.index') }}" data-async-link class="font-semibold text-brand-accent hover:text-brand-accent-dark">Clear search</a>
        </p>
    @endif

    @if ($posts->isEmpty())
        <div class="rounded-3xl border border-dashed border-brand-navy/15 bg-white px-6 py-16 text-center">
            <p class="text-lg font-semibold text-brand-navy">No articles {{ $search !== '' ? 'match your search' : 'here yet' }}.</p>
            <p class="mt-2 text-sm text-brand-navy/60">{{ $search !== '' ? 'Try a different keyword.' : 'Check back soon — new posts are on the way.' }}</p>
        </div>
    @else
        <div @class([
            'grid gap-6 lg:gap-8',
            'sm:grid-cols-2 lg:grid-cols-3' => $blog->get('layout') !== 'list',
            'grid-cols-1' => $blog->get('layout') === 'list',
        ])>
            @foreach ($posts as $post)
                <x-blog.card
                    :post="$post"
                    :style="$blog->get('card_style')"
                    :layout="$blog->get('layout')"
                    :show-author="$blog->get('show_author')"
                    :show-reading-time="$blog->get('show_reading_time')"
                    :show-category="$categoriesOn && ! $activeCategory"
                    heading-level="h2"
                />
            @endforeach
        </div>

        @if ($posts->hasPages())
            <nav class="mt-12 flex items-center justify-center gap-2" aria-label="Pagination">
                @if ($posts->onFirstPage())
                    <span class="rounded-xl border border-brand-navy/10 px-4 py-2 text-sm text-brand-navy/35">Previous</span>
                @else
                    <a href="{{ $posts->previousPageUrl() }}" rel="prev" data-async-link class="rounded-xl border border-brand-navy/15 bg-white px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-accent hover:text-brand-accent">Previous</a>
                @endif

                <ul class="hidden items-center gap-1 sm:flex">
                    @foreach ($posts->getUrlRange(max(1, $posts->currentPage() - 2), min($posts->lastPage(), $posts->currentPage() + 2)) as $page => $url)
                        <li>
                            @if ($page === $posts->currentPage())
                                <span aria-current="page" class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-accent text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" data-async-link class="flex h-10 w-10 items-center justify-center rounded-xl text-sm font-medium text-brand-navy transition hover:bg-brand-sky hover:text-brand-accent">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <span class="text-sm text-brand-navy/60 sm:hidden">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>

                @if ($posts->hasMorePages())
                    <a href="{{ $posts->nextPageUrl() }}" rel="next" data-async-link class="rounded-xl border border-brand-navy/15 bg-white px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-accent hover:text-brand-accent">Next</a>
                @else
                    <span class="rounded-xl border border-brand-navy/10 px-4 py-2 text-sm text-brand-navy/35">Next</span>
                @endif
            </nav>
        @endif
    @endif
</div>
