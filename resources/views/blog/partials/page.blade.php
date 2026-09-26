{{--
    Everything between the header and footer on a blog listing (landing,
    category, tag, search). It is the async region: category chips,
    search and pagination fetch() the next URL and swap this block in
    place (resources/js/app.js, [data-async-region]), updating the title
    and URL. Returned on its own for fetch() requests — see
    BlogController::listing().
--}}
<div data-async-region="blog" data-page-title="{{ $seo['title'] }}">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark pt-28 pb-16 text-white sm:pt-36 sm:pb-20">
        <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-24 -z-10 h-80 w-80 rounded-full bg-brand-accent-light/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <x-blog.breadcrumbs :items="$breadcrumbs" :dark="true"/>

            <div class="mt-8 grid items-end gap-8 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-white/85 uppercase backdrop-blur">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        {{ $eyebrow }}
                    </p>
                    <h1 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl">{{ $heading }}</h1>
                    @if ($intro)
                        <p class="mt-4 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">{{ $intro }}</p>
                    @endif
                </div>

                <form method="GET" action="{{ route('blog.index') }}" role="search" data-async-form class="lg:col-span-2">
                    <label for="blog-search" class="sr-only">Search articles</label>
                    <div class="flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 p-1.5 backdrop-blur focus-within:border-white/40 focus-within:bg-white/15">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-3 h-5 w-5 shrink-0 text-white/60" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                        <input id="blog-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search articles…"
                            class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-sm text-white placeholder:text-white/50 focus:ring-0 focus:outline-none">
                        <button type="submit" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-brand-navy transition hover:bg-brand-sky">Search</button>
                    </div>
                </form>
            </div>

            @if ($categoriesOn && $categories->isNotEmpty())
                <nav aria-label="Blog categories" class="mt-10 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <ul class="flex w-max gap-2 sm:w-auto sm:flex-wrap">
                        <li>
                            <a href="{{ route('blog.index') }}" data-async-link @class([
                                'inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition',
                                'bg-white text-brand-navy' => ! $activeCategory && ! $activeTag && $search === '',
                                'border border-white/20 text-white/85 hover:border-white/50 hover:text-white' => $activeCategory || $activeTag || $search !== '',
                            ])>All articles</a>
                        </li>
                        @foreach ($categories as $category)
                            <li>
                                <a href="{{ $category->url() }}" data-async-link @class([
                                    'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition',
                                    'bg-white text-brand-navy' => $activeCategory?->is($category),
                                    'border border-white/20 text-white/85 hover:border-white/50 hover:text-white' => ! $activeCategory?->is($category),
                                ])>
                                    {{ $category->name }}
                                    <span class="text-xs opacity-60">{{ $category->posts_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </section>

    <section class="bg-brand-mist py-14 sm:py-16" id="articles">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            @if ($featured)
                @php $cover = $featured->cover; @endphp
                <article class="group relative mb-12 grid overflow-hidden rounded-3xl bg-white shadow-xl shadow-brand-navy/5 lg:grid-cols-2">
                    <div class="relative aspect-[16/10] overflow-hidden lg:aspect-auto lg:min-h-96">
                        @if ($cover)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($cover->disk)->url($cover->path) }}" alt="{{ $cover->alt_text ?: '' }}" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" fetchpriority="high">
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br from-brand-navy via-brand-accent-dark to-brand-accent" aria-hidden="true"></div>
                        @endif
                    </div>
                    <div class="flex flex-col justify-center p-7 sm:p-10">
                        <p class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-wider text-brand-accent uppercase">
                            <span class="rounded-full bg-brand-sky px-3 py-1">Featured</span>
                            @if ($categoriesOn && $featured->category)
                                <span>{{ $featured->category->name }}</span>
                            @endif
                        </p>
                        <h2 class="mt-4 text-2xl leading-tight font-bold text-brand-navy transition group-hover:text-brand-accent sm:text-3xl">
                            <a href="{{ $featured->url() }}" class="after:absolute after:inset-0">{{ $featured->title }}</a>
                        </h2>
                        @if ($featured->excerpt)
                            <p class="mt-4 leading-relaxed text-brand-navy/70">{{ $featured->excerpt }}</p>
                        @endif
                        <p class="mt-6 flex flex-wrap items-center gap-x-2 text-sm text-brand-navy/55">
                            @if ($blog->get('show_author') && $featured->author)
                                <span class="font-medium text-brand-navy/80">{{ $featured->author->name }}</span>
                                <span aria-hidden="true">·</span>
                            @endif
                            <time datetime="{{ $featured->published_at?->toDateString() }}">{{ $featured->published_at?->format('M j, Y') }}</time>
                            @if ($blog->get('show_reading_time'))
                                <span aria-hidden="true">·</span>
                                <span>{{ $featured->reading_minutes }} min read</span>
                            @endif
                        </p>
                        <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-accent" aria-hidden="true">
                            Read the article <x-site.arrow class="h-4 w-4 transition group-hover:translate-x-1"/>
                        </span>
                    </div>
                </article>
            @endif

            @include('blog.partials.results')

            @if ($newsletterOn)
                <x-blog.newsletter class="mt-16"/>
            @endif
        </div>
    </section>
</div>
