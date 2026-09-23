{{--
    Bespoke public renderer for the About CMS Page (PageTemplate::About only
    — every other template still renders through pages.show unchanged, see
    PageContentRenderer). Reads the same Page/PageSection data pages.show
    reads; nothing here is hardcoded content. "About All the Things Light"
    (matched by title) gets its own lede/idea-box/closing treatment and
    shows a leading image-only paragraph above its heading; every other
    rich-text section (About Cory Gold, About Music, About Jacob d'IAWARII,
    or anything an admin adds) shares one eyebrow-heading card, in the
    admin-set section order. Non-rich-text blocks render a placeholder so
    nothing an admin adds is ever silently dropped.
--}}
@php
    use App\Enums\MediaCategory;
    use App\Enums\PageSectionType;
    use App\Models\Media;
    use Illuminate\Support\Facades\Storage;

    $sections = $page->sections->where('is_enabled', true)->sortBy('sort_order')->values();

    // Presentation-only split of the "About All the Things Light" rich-text
    // body into paragraphs, so the opening line, the central ideas, and the
    // closing line can each get their own visual treatment. The supplied
    // copy itself is never altered.
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

    // The first paragraph is always the lede and the last is always the
    // closing line — purely positional, so editing their wording never
    // breaks anything. The idea boxes in between are found by two fixed
    // marker paragraphs that bracket them ($ideasIntro / $ideasOutro)
    // rather than by matching the idea sentences' own wording — an earlier
    // version matched each idea paragraph against its exact original text,
    // which meant editing an idea's copy to anything else made that box
    // (and its text) disappear from the "ideas" treatment entirely. Editing
    // the intro/outro marker sentences themselves still falls back to no
    // idea-box grouping (paragraphs render normally) rather than breaking.
    $ideasIntro = 'At the heart of it all are a few ideas:';
    $ideasOutro = 'All the Things Light is an invitation to experience those ideas—not only through words, but through music, conversation, gratitude, creativity, and connection.';

    $buildChunks = function (array $paragraphs) use ($ideasIntro, $ideasOutro): array {
        $count = count($paragraphs);

        if ($count === 0) {
            return [];
        }

        $roles = array_fill(0, $count, 'normal');
        $roles[0] = 'lede';
        if ($count > 1) {
            $roles[$count - 1] = 'closing';
        }

        $introIndex = null;
        $outroIndex = null;
        foreach ($paragraphs as $i => $paragraph) {
            if ($paragraph['text'] === $ideasIntro) {
                $introIndex = $i;
            } elseif ($paragraph['text'] === $ideasOutro) {
                $outroIndex = $i;
            }
        }

        if ($introIndex !== null && $outroIndex !== null && $outroIndex > $introIndex + 1) {
            for ($i = $introIndex + 1; $i < $outroIndex; $i++) {
                $roles[$i] = 'idea';
            }
        }

        $chunks = [];
        $ideaBuffer = [];

        foreach ($paragraphs as $i => $paragraph) {
            if ($roles[$i] === 'idea') {
                $ideaBuffer[] = $paragraph;

                continue;
            }

            if ($ideaBuffer) {
                $chunks[] = ['type' => 'ideas', 'items' => $ideaBuffer];
                $ideaBuffer = [];
            }

            $chunks[] = ['type' => $roles[$i], 'item' => $paragraph];
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
                @php
                    $content = $section->content_json ?? [];
                    $video = ! empty($content['video_media_id']) ? Media::find($content['video_media_id']) : null;
                    $hasVideo = $video && $video->category() === MediaCategory::Video;
                    $videoBeforeContent = ($content['video_position'] ?? 'before_content') !== 'after_content';
                @endphp

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
                        @php
                            // A body that opens with an image-only paragraph
                            // (the client's portrait, inserted through the rich
                            // editor) shows that image above the section heading
                            // instead of as the "lede" — client-requested order:
                            // picture, then heading, then text. The lede role
                            // then falls to the first real text paragraph.
                            $paragraphs = $splitParagraphs($content['body'] ?? '');
                            $leadImage = null;
                            if ($paragraphs && $paragraphs[0]['text'] === '' && str_contains($paragraphs[0]['html'], '<img')) {
                                $leadImage = array_shift($paragraphs)['html'];
                            }
                        @endphp

                        <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-brand-navy/5 sm:p-12">
                            @if ($leadImage)
                                <div data-section-lead-image class="mx-auto mb-10 max-w-sm overflow-hidden rounded-2xl shadow-lg ring-1 ring-brand-navy/10 [&_img]:block [&_img]:h-auto [&_img]:w-full">
                                    {!! $leadImage !!}
                                </div>
                            @endif

                            <div class="text-center">
                                <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $section->title }}</span>
                                <div class="mx-auto mt-4 mb-8 flex items-center justify-center gap-3" aria-hidden="true">
                                    <span class="h-px w-10 bg-brand-gold/60"></span>
                                    <span class="text-sm text-brand-gold">✦</span>
                                    <span class="h-px w-10 bg-brand-gold/60"></span>
                                </div>
                            </div>

                            @if ($videoBeforeContent)
                                @include('pages.partials.about-video')
                            @endif

                            @php $chunks = $buildChunks($paragraphs); @endphp

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

                            @unless ($videoBeforeContent)
                                <div class="mt-8">@include('pages.partials.about-video')</div>
                            @endunless
                        </div>
                    @else
                        {{-- Every other rich-text section (About Cory Gold,
                            About Music, About Jacob d'IAWARII, or anything an
                            admin adds later) shares one card treatment:
                            eyebrow heading, optional video, then body. --}}
                        <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-brand-navy/5 sm:p-12">
                            @if ($section->title)
                                <div class="text-center">
                                    <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $section->title }}</span>
                                    <div class="mx-auto mt-4 mb-8 flex items-center justify-center gap-3" aria-hidden="true">
                                        <span class="h-px w-10 bg-brand-gold/60"></span>
                                        <span class="text-sm text-brand-gold">✦</span>
                                        <span class="h-px w-10 bg-brand-gold/60"></span>
                                    </div>
                                </div>
                            @endif

                            @if ($videoBeforeContent)
                                @include('pages.partials.about-video')
                            @endif

                            {{-- [&_p:last-child]:mb-0 trims only the final
                                paragraph's gap. The previous last:[&_p]:mb-0
                                matched whenever this wrapper was the card's
                                last child, zeroing every paragraph's margin. --}}
                            @php $hasBody = trim(strip_tags($content['body'] ?? '', '<img>')) !== ''; @endphp
                            <div data-section-body @class(['mx-auto max-w-xl text-left [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-brand-navy/80 [&_p:last-child]:mb-0', 'mt-8' => $hasBody && $hasVideo && $videoBeforeContent])>
                                @if ($hasBody)
                                    {!! $content['body'] !!}
                                @endif
                            </div>

                            @unless ($videoBeforeContent)
                                <div class="mt-8">@include('pages.partials.about-video')</div>
                            @endunless
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
