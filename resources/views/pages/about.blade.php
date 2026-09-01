{{--
    Bespoke public renderer for the About CMS Page (PageTemplate::About only
    — every other template still renders through pages.show unchanged, see
    PageContentRenderer). Reads the same Page/PageSection data pages.show
    reads; nothing here is hardcoded content — only presentation is chosen
    per section, matched by the section's own title, so the three required
    About sections (About All the Things Light / About Cory Gold / About
    Jacob d'IAWARII) get distinct visual treatment while staying fully
    admin-editable. Any section title/type outside that known set falls
    back to a plain generic card so nothing an admin adds is ever silently
    dropped.
--}}
@php
    use App\Enums\MediaCategory;
    use App\Enums\PageSectionType;
    use App\Models\Media;
    use Illuminate\Support\Facades\Storage;

    $sections = $page->sections->where('is_enabled', true)->sortBy('sort_order')->values();

    // Presentation-only split of the "About All the Things Light" rich-text
    // body into paragraphs, so the opening line, the three central ideas,
    // and the closing line can each get their own visual treatment. The
    // supplied copy itself is never altered — a paragraph that doesn't
    // match one of the known lines below simply renders as a normal
    // paragraph, so future edits degrade gracefully instead of breaking.
    $splitParagraphs = function (string $html): array {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div>'.$html.'</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $paragraphs = [];
        foreach ($dom->getElementsByTagName('p') as $p) {
            $inner = '';
            foreach ($p->childNodes as $child) {
                $inner .= $dom->saveHTML($child);
            }
            $paragraphs[] = ['html' => $inner, 'text' => trim($p->textContent)];
        }

        return $paragraphs;
    };

    $lede = 'All the Things Light is a place to come and gather.';
    $ideaStatements = [
        'Love is creation’s greatest idea.',
        'We are one.',
        'The closer we move toward the light, the more we begin to look like the light.',
    ];
    $closing = 'Come and Gather. ✨';

    $buildChunks = function (array $paragraphs) use ($ideaStatements, $lede, $closing): array {
        $chunks = [];
        $ideaBuffer = [];

        foreach ($paragraphs as $paragraph) {
            if (in_array($paragraph['text'], $ideaStatements, true)) {
                $ideaBuffer[] = $paragraph;

                continue;
            }

            if ($ideaBuffer) {
                $chunks[] = ['type' => 'ideas', 'items' => $ideaBuffer];
                $ideaBuffer = [];
            }

            $type = match ($paragraph['text']) {
                $lede => 'lede',
                $closing => 'closing',
                default => 'normal',
            };

            $chunks[] = ['type' => $type, 'item' => $paragraph];
        }

        if ($ideaBuffer) {
            $chunks[] = ['type' => 'ideas', 'items' => $ideaBuffer];
        }

        return $chunks;
    };
@endphp

<x-layouts.site :seo="$seo">
    @unless ($page->status->value === 'published')
        <div class="bg-amber-50 px-4 py-3 text-center text-sm font-semibold text-amber-800">
            Preview only — this page is currently <strong>{{ $page->status->getLabel() }}</strong> and is not visible to the public.
        </div>
    @endunless

    @if ($showChrome)
        <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>
    @endif

    <div class="relative isolate overflow-hidden bg-brand-ivory">
        @if ($page->featuredImage)
            <img
                src="{{ Storage::disk($page->featuredImage->disk)->url($page->featuredImage->path) }}"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover opacity-15"
            >
            <div class="absolute inset-0 bg-brand-ivory/70"></div>
        @endif

        <div class="relative mx-auto max-w-3xl px-4 pt-32 pb-16 text-center sm:px-6 sm:pt-36 lg:px-8">
            <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">{{ $page->title }}</span>
            <h1 class="mt-3 font-serif text-4xl text-brand-navy sm:text-5xl">{{ $siteName }}</h1>
            <div class="mx-auto mt-6 flex items-center justify-center gap-3" aria-hidden="true">
                <span class="h-px w-14 bg-brand-gold/70"></span>
                <span class="text-brand-gold">✦</span>
                <span class="h-px w-14 bg-brand-gold/70"></span>
            </div>
        </div>
    </div>

    <main class="bg-brand-ivory">
        <div class="mx-auto max-w-3xl px-4 pb-24 sm:px-6 lg:px-8">
            @forelse ($sections as $index => $section)
                @php $content = $section->content_json ?? []; @endphp

                @if ($index > 0)
                    <div class="my-14 flex items-center justify-center gap-3" aria-hidden="true">
                        <span class="h-px w-10 bg-brand-navy/10"></span>
                        <span class="text-sm text-brand-gold/70">✦</span>
                        <span class="h-px w-10 bg-brand-navy/10"></span>
                    </div>
                @endif

                <section class="about-section" data-section="{{ $section->title }}">
                    @if ($section->section_type !== PageSectionType::RichText->value)
                        <p class="rounded-xl border border-dashed border-brand-navy/20 p-6 text-center text-sm text-brand-navy/50">
                            {{ $section->title ?: PageSectionType::from($section->section_type)->getLabel() }} — no rendering yet for this block type.
                        </p>
                    @elseif ($section->title === 'About All the Things Light')
                        <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-brand-navy/5 sm:p-12">
                            <div class="text-center">
                                <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $section->title }}</span>
                                <div class="mx-auto mt-4 mb-8 flex items-center justify-center gap-3" aria-hidden="true">
                                    <span class="h-px w-10 bg-brand-gold/60"></span>
                                    <span class="text-sm text-brand-gold">✦</span>
                                    <span class="h-px w-10 bg-brand-gold/60"></span>
                                </div>
                            </div>

                            @php $chunks = $buildChunks($splitParagraphs($content['body'] ?? '')); @endphp

                            <div data-section-body>
                                @foreach ($chunks as $chunk)
                                    @switch($chunk['type'])
                                        @case('lede')
                                            <p class="mx-auto max-w-xl text-center font-serif text-2xl leading-snug text-brand-navy sm:text-3xl">
                                                {!! $chunk['item']['html'] !!}
                                            </p>
                                            @break

                                        @case('ideas')
                                            <div class="my-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                @foreach ($chunk['items'] as $idea)
                                                    <div class="rounded-2xl bg-brand-ivory p-6 text-center ring-1 ring-brand-gold/20">
                                                        <p class="font-serif text-lg text-brand-navy [text-wrap:balance]">{!! $idea['html'] !!}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @break

                                        @case('closing')
                                            <p class="mt-10 text-center font-serif text-2xl text-brand-gold">
                                                {!! $chunk['item']['html'] !!}
                                            </p>
                                            @break

                                        @default
                                            <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-brand-navy/80">
                                                {!! $chunk['item']['html'] !!}
                                            </p>
                                    @endswitch
                                @endforeach
                            </div>
                        </div>
                    @elseif ($section->title === 'About Cory Gold')
                        <div class="py-4 text-center">
                            <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $section->title }}</span>
                            <div class="mx-auto mt-4 flex items-center justify-center gap-3" aria-hidden="true">
                                <span class="h-px w-10 bg-brand-gold/60"></span>
                                <span class="text-sm text-brand-gold">✦</span>
                                <span class="h-px w-10 bg-brand-gold/60"></span>
                            </div>
                            <div data-section-body class="mx-auto mt-8 max-w-xl text-left [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-brand-navy/80 last:[&_p]:mb-0">
                                @if (trim(strip_tags($content['body'] ?? '')) !== '')
                                    {!! $content['body'] !!}
                                @endif
                            </div>
                        </div>
                    @elseif ($section->title === "About Jacob d'IAWARII")
                        @php
                            $video = ! empty($content['video_media_id']) ? Media::find($content['video_media_id']) : null;
                            $hasVideo = $video && $video->category() === MediaCategory::Video;
                        @endphp
                        <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-brand-navy/5 sm:p-12">
                            <div class="text-center">
                                <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $section->title }}</span>
                                <div class="mx-auto mt-4 mb-8 flex items-center justify-center gap-3" aria-hidden="true">
                                    <span class="h-px w-10 bg-brand-gold/60"></span>
                                    <span class="text-sm text-brand-gold">✦</span>
                                    <span class="h-px w-10 bg-brand-gold/60"></span>
                                </div>
                            </div>

                            @if ($hasVideo)
                                <div class="mx-auto max-w-2xl overflow-hidden rounded-2xl bg-brand-navy-dark shadow-lg ring-1 ring-brand-navy/10">
                                    <video controls playsinline preload="none" class="aspect-video w-full">
                                        <source src="{{ route('media.watch', $video) }}" type="{{ $video->mime_type }}">
                                    </video>
                                </div>
                            @endif

                            <div data-section-body class="mx-auto mt-8 max-w-xl text-left [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-brand-navy/80 last:[&_p]:mb-0">
                                @if (trim(strip_tags($content['body'] ?? '')) !== '')
                                    {!! $content['body'] !!}
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-brand-navy/5 sm:p-12">
                            @if ($section->title)
                                <h2 class="text-center font-serif text-2xl text-brand-navy">{{ $section->title }}</h2>
                            @endif
                            <div data-section-body class="mx-auto mt-6 max-w-xl text-left [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-brand-navy/80 last:[&_p]:mb-0">
                                {!! $content['body'] ?? '' !!}
                            </div>
                        </div>
                    @endif
                </section>
            @empty
                <p class="text-center text-sm text-brand-navy/60">This page has no sections yet.</p>
            @endforelse
        </div>
    </main>

    @if ($showChrome)
        <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
    @endif
</x-layouts.site>
