{{--
    Image + Text section's full-width styles:

    - split / split-reverse: image on one side (with a soft accent frame),
      eyebrow + heading + paragraphs + optional button on the other.
    - profile: a person card — portrait, name (heading), role (subheading)
      and their words as a quote (text), e.g. "Meet the Founder".

    Paragraphs come from the Text field, separated by blank lines.
--}}
@props(['section', 'content', 'first' => false])

@php
    $style = $content['style'] ?? 'split';
    $image = !empty($content['media_id']) ? \App\Models\Media::find($content['media_id']) : null;
    $imageUrl = $image ? \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) : null;
    $paragraphs = collect(preg_split('/\R\s*\R/', trim((string) ($content['text'] ?? ''))))->map(fn ($p) => trim($p))->filter()->values();
    $hasButton = !empty($content['cta_label']) && !empty($content['cta_url']);
    $initials = \Illuminate\Support\Str::of($content['heading'] ?? '?')->explode(' ')->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
@endphp

<x-site.band :background="$content['background'] ?? 'white'" :anchor="$content['anchor'] ?? null">
    @if ($style === 'profile')
        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-navy via-brand-navy to-brand-accent-dark p-8 text-white shadow-2xl shadow-brand-navy/20 sm:p-12 lg:p-14">
            <div class="pointer-events-none absolute -top-20 -right-20 h-72 w-72 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
            <div class="relative grid items-center gap-10 lg:grid-cols-12">
                <div class="flex justify-center lg:col-span-4">
                    <div class="relative">
                        <div class="absolute -inset-2 rounded-[1.75rem] bg-gradient-to-br from-brand-accent-light to-brand-accent opacity-60 blur-md" aria-hidden="true"></div>
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $image->alt_text ?: ($content['heading'] ?? '') }}" loading="lazy" class="relative aspect-square w-56 rounded-3xl object-cover ring-4 ring-white/15 sm:w-64">
                        @else
                            <div class="relative flex aspect-square w-56 items-center justify-center rounded-3xl bg-white/10 text-6xl font-bold ring-4 ring-white/15 sm:w-64" aria-hidden="true">{{ $initials }}</div>
                        @endif
                    </div>
                </div>
                <div class="lg:col-span-8">
                    @if (!empty($content['eyebrow']))
                        <p class="text-xs font-semibold tracking-[0.18em] text-brand-accent-light uppercase">{{ $content['eyebrow'] }}</p>
                    @endif
                    @if (!empty($content['heading']))
                        <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['heading'] }}</h2>
                    @endif
                    @if (!empty($content['subheading']))
                        <p class="mt-1 font-medium text-white/70">{{ $content['subheading'] }}</p>
                    @endif
                    @if ($paragraphs->isNotEmpty())
                        <blockquote class="relative mt-6 space-y-4 border-l-4 border-brand-accent-light/70 pl-6 text-lg leading-relaxed text-white/85">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute -top-2 -left-3 h-8 w-8 -translate-x-full text-white/10" aria-hidden="true"><path d="M9.5 6C6.5 6 4 8.5 4 11.5V18h6v-6H7c0-1.7 1.3-3 3-3V6h-.5zm10 0C16.5 6 14 8.5 14 11.5V18h6v-6h-3c0-1.7 1.3-3 3-3V6h-.5z"/></svg>
                            @foreach ($paragraphs as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </blockquote>
                    @endif
                    @if ($hasButton)
                        <x-site.button :href="$content['cta_url']" size="lg" variant="light" class="mt-8">
                            {{ $content['cta_label'] }} <x-site.arrow class="h-4 w-4"/>
                        </x-site.button>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            @if ($imageUrl)
                <div @class(['relative', 'lg:order-2' => $style === 'split-reverse'])>
                    <div @class([
                        'absolute -z-0 h-full w-full rounded-3xl bg-gradient-to-br from-brand-sky to-brand-ice-deep',
                        '-right-4 -bottom-4 sm:-right-6 sm:-bottom-6' => $style !== 'split-reverse',
                        '-bottom-4 -left-4 sm:-bottom-6 sm:-left-6' => $style === 'split-reverse',
                    ]) aria-hidden="true"></div>
                    <img src="{{ $imageUrl }}" alt="{{ $image->alt_text ?: '' }}" loading="lazy" class="relative aspect-[4/3] w-full rounded-3xl object-cover shadow-xl shadow-brand-navy/10">
                </div>
            @endif
            <div @class(['lg:col-span-2' => ! $imageUrl, 'max-w-3xl' => ! $imageUrl])>
                @if (!empty($content['eyebrow']))
                    <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">{{ $content['eyebrow'] }}</p>
                @endif
                @if (!empty($content['heading']))
                    <h2 class="mt-2 text-3xl leading-tight font-bold tracking-tight whitespace-pre-line text-brand-navy sm:text-4xl">{{ $content['heading'] }}</h2>
                @endif
                @if (!empty($content['subheading']))
                    <p class="mt-3 text-lg font-medium text-brand-navy/80">{{ $content['subheading'] }}</p>
                @endif
                @foreach ($paragraphs as $paragraph)
                    <p @class(['leading-relaxed text-brand-navy/70', 'mt-5' => $loop->first, 'mt-4' => ! $loop->first])>{{ $paragraph }}</p>
                @endforeach
                @if ($hasButton)
                    <x-site.button :href="$content['cta_url']" size="lg" class="mt-8">
                        {{ $content['cta_label'] }} <x-site.arrow class="h-4 w-4"/>
                    </x-site.button>
                @endif
            </div>
        </div>
    @endif
</x-site.band>
