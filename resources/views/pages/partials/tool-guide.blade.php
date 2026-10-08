{{--
    Layout for CMS pages flagged as tool guides (served at /tools/{slug} —
    see ToolController::show()), e.g. the Social Video Safe Zone Checker's
    platform guides: a title header with an organisation byline, the Rich
    Text sections in long-form typography (.blog-prose, so tables, lists
    and figures read well), a table of contents from their Heading 2s, and
    any other sections (e.g. a Call to Action) after the article.
--}}
@php
    $visible = $page->sections->where('is_enabled', true)->sortBy('sort_order');
    $body = $visible
        ->where('section_type', \App\Enums\PageSectionType::RichText->value)
        ->map(fn ($section) => $section->content_json['body'] ?? '')
        ->implode("\n");
    $prepared = \App\Shared\Support\Blog\BlogContent::prepare($body);
    $toc = array_values(array_filter($prepared['toc'], fn (array $entry): bool => $entry['level'] === 2));
    $others = $visible->where('section_type', '!=', \App\Enums\PageSectionType::RichText->value);
@endphp

<main class="flex-1">
    <header class="border-b border-brand-navy/5 bg-gradient-to-b from-brand-sky to-white pt-28 pb-12 sm:pt-36">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">Guide</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight text-brand-navy sm:text-5xl">{{ $page->title }}</h1>
            @if ($page->summary)
                <p class="mt-4 max-w-3xl text-lg leading-relaxed text-brand-navy/70">{{ $page->summary }}</p>
            @endif
            <p class="mt-5 text-sm text-brand-navy/60">
                By {{ $siteName }} · Updated <time datetime="{{ $page->updated_at?->toDateString() }}">{{ $page->updated_at?->format('j F Y') }}</time>
            </p>
        </div>
    </header>

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-12 sm:px-6 lg:grid-cols-12">
        @if ($toc !== [])
            <aside class="lg:col-span-4 lg:order-2" aria-label="On this page">
                <div class="lg:sticky lg:top-28">
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
                </div>
            </aside>
        @endif

        <article @class(['min-w-0', 'lg:col-span-8 lg:order-1' => $toc !== [], 'lg:col-span-8 lg:col-start-3' => $toc === []])>
            <div class="blog-prose">{!! $prepared['html'] !!}</div>
        </article>
    </div>

    @if ($others->isNotEmpty())
        <x-site.sections :sections="$others"/>
    @endif
</main>
