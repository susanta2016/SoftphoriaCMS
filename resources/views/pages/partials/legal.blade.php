{{--
    Layout for Legal/Policy-template CMS pages (Privacy Policy, Terms of
    Service, Cookie Policy…): a calm header with the last-updated date, the
    document in readable long-form typography (.blog-prose), a sticky table
    of contents built from its Heading 2s, and links to the sibling
    policies. The content is still the page's own Rich Text section(s),
    edited in Admin → Pages.
--}}
@php
    $body = $page->sections
        ->where('is_enabled', true)
        ->sortBy('sort_order')
        ->where('section_type', \App\Enums\PageSectionType::RichText->value)
        ->map(fn ($section) => $section->content_json['body'] ?? '')
        ->implode("\n");
    $prepared = \App\Shared\Support\Blog\BlogContent::prepare($body);
    $toc = array_values(array_filter($prepared['toc'], fn (array $entry): bool => $entry['level'] === 2));

    $siblings = \App\Models\Page::query()
        ->published()
        ->where('template', \App\Enums\PageTemplate::Legal->value)
        ->whereKeyNot($page->getKey())
        ->orderBy('title')
        ->get(['id', 'title', 'slug', 'summary']);
@endphp

<main class="flex-1">
    <header class="border-b border-brand-navy/5 bg-gradient-to-b from-brand-sky to-white pt-28 pb-12 sm:pt-36">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <x-blog.breadcrumbs :items="[['label' => $page->title, 'url' => url()->current()]]"/>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-brand-navy sm:text-5xl">{{ $page->title }}</h1>
            @if ($page->summary)
                <p class="mt-4 max-w-3xl text-lg leading-relaxed text-brand-navy/70">{{ $page->summary }}</p>
            @endif
            <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-4 py-1.5 text-sm text-brand-navy/65 shadow-sm ring-1 ring-brand-navy/5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-brand-accent" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                Last updated <time datetime="{{ $page->updated_at->toDateString() }}" class="font-semibold text-brand-navy">{{ $page->updated_at->format('j F Y') }}</time>
            </p>
        </div>
    </header>

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-12 sm:px-6 lg:grid-cols-12">
        @if ($toc !== [])
            <aside class="lg:col-span-4 lg:order-2" aria-label="On this page">
                <div class="space-y-6 lg:sticky lg:top-28">
                    <details class="rounded-2xl border border-brand-navy/10 bg-white p-5 shadow-sm lg:open:p-6" open>
                        <summary class="cursor-pointer text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase lg:pointer-events-none lg:list-none lg:[&::-webkit-details-marker]:hidden">On this page</summary>
                        <ol class="mt-3 max-h-[60vh] space-y-0.5 overflow-y-auto text-sm" data-blog-toc>
                            @foreach ($toc as $entry)
                                <li>
                                    <a href="#{{ $entry['id'] }}" class="block rounded-lg border-l-2 border-transparent px-3 py-1.5 text-brand-navy/65 transition hover:border-brand-accent hover:bg-brand-mist hover:text-brand-navy data-[active]:border-brand-accent data-[active]:bg-brand-sky data-[active]:font-semibold data-[active]:text-brand-accent">{{ $entry['text'] }}</a>
                                </li>
                            @endforeach
                        </ol>
                    </details>

                    @if ($siblings->isNotEmpty())
                        <nav class="rounded-2xl border border-brand-navy/10 bg-white p-6 shadow-sm" aria-label="Related policies">
                            <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Related policies</p>
                            <ul class="mt-3 space-y-1">
                                @foreach ($siblings as $sibling)
                                    <li>
                                        <a href="{{ route('pages.show', $sibling) }}" class="group flex items-center justify-between gap-3 rounded-xl px-2 py-2 text-sm font-medium text-brand-navy/80 transition hover:bg-brand-mist hover:text-brand-accent">
                                            {{ $sibling->title }}
                                            <x-site.arrow class="h-3.5 w-3.5 opacity-0 transition group-hover:opacity-100"/>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                </div>
            </aside>
        @endif

        <article @class(['min-w-0', 'lg:col-span-8 lg:order-1' => $toc !== [], 'lg:col-span-8 lg:col-start-3' => $toc === []])>
            <div class="blog-prose">{!! $prepared['html'] !!}</div>

            <div class="mt-14 flex flex-col items-start gap-4 rounded-2xl border border-brand-accent/15 bg-brand-sky/60 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-brand-navy">Questions about this policy?</p>
                    <p class="mt-1 text-sm text-brand-navy/65">Our team is happy to help — reach out any time.</p>
                </div>
                <a href="{{ route('contact.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-accent-dark">
                    Contact us <x-site.arrow class="h-4 w-4"/>
                </a>
            </div>
        </article>
    </div>
</main>
