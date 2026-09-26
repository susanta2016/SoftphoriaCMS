{{--
    WEB-103 — the Testimonials section type: every enabled row from the
    admin Testimonials resource, in sort order, as a one-at-a-time slider
    over the section's background image. The slides sit side by side in a
    horizontal track; JS (resources/js/app.js, [data-testimonial-slider])
    slides it, wires the arrows/dots, and autoplays every
    `autoplay_seconds` in an endless loop (last → first keeps sliding the
    same way, no rewind), pausing on hover/focus and never autoplaying for
    prefers-reduced-motion. Without JS the first slide simply shows.
--}}
@props(['section', 'content'])

@php
    // Hidden entirely while Features Activation has Testimonials off.
    $testimonials = app(\App\Shared\Support\Features\Features::class)->enabled('testimonials')
        ? \App\Models\Testimonial::query()
            ->with('avatar')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
        : collect();

    $background = !empty($content['background_media_id']) ? \App\Models\Media::find($content['background_media_id']) : null;
    $backgroundUrl = $background ? \Illuminate\Support\Facades\Storage::disk($background->disk)->url($background->path) : null;

    // Seconds per slide (Pages → Home → Testimonials); 0 turns autoplay off.
    // Sections saved before this setting existed default to 6.
    $autoplaySeconds = $testimonials->count() > 1 ? max(0, (int) ($content['autoplay_seconds'] ?? 6)) : 0;
@endphp

@if ($testimonials->isNotEmpty())
    <section
        @if (!empty($content['anchor'])) id="{{ $content['anchor'] }}" @endif
        class="relative isolate scroll-mt-24 overflow-hidden bg-brand-navy-dark bg-cover bg-bottom py-14 sm:py-16 lg:py-20"
        @style([$backgroundUrl ? "background-image: url('{$backgroundUrl}')" : ''])
        data-testimonial-slider
        data-autoplay="{{ $autoplaySeconds }}"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-6">
                <x-site.section-heading :eyebrow="$content['eyebrow'] ?? null" :heading="$content['heading'] ?? null" :dark="true"/>

                @if ($testimonials->count() > 1)
                    <div class="flex shrink-0 gap-2">
                        <button type="button" data-testimonial-prev aria-label="Previous testimonial" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/40 text-white transition hover:border-white hover:bg-white/10">
                            <x-site.arrow class="h-4 w-4 rotate-180"/>
                        </button>
                        <button type="button" data-testimonial-next aria-label="Next testimonial" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/40 text-white transition hover:border-white hover:bg-white/10">
                            <x-site.arrow class="h-4 w-4"/>
                        </button>
                    </div>
                @endif
            </div>

            {{--
                A flex row stretches every slide to the tallest quote, so the
                section never jumps in height. aria-live is switched to "off"
                by JS while autoplaying, so screen readers aren't interrupted
                every few seconds.
            --}}
            <div class="mx-auto mt-8 max-w-3xl overflow-hidden">
                <div data-testimonial-track class="flex transition-transform duration-700 ease-in-out motion-reduce:transition-none" aria-live="polite">
                    @foreach ($testimonials as $testimonial)
                        <figure data-testimonial-slide class="w-full shrink-0 pt-4 text-center">
                            <blockquote class="relative px-6 text-base leading-relaxed text-white/90 sm:px-10 sm:text-lg">
                                <span class="absolute -top-3 left-0 font-serif text-5xl leading-none text-white/70" aria-hidden="true">&ldquo;</span>
                                {{ $testimonial->message }}
                                <span class="absolute right-0 -bottom-7 font-serif text-5xl leading-none text-white/70" aria-hidden="true">&rdquo;</span>
                            </blockquote>
                            <figcaption class="mt-8 flex items-center justify-center gap-3 text-left">
                                @if ($testimonial->avatar)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($testimonial->avatar->disk)->url($testimonial->avatar->path) }}" alt="" class="h-14 w-14 shrink-0 rounded-full border-2 border-white/80 object-cover" loading="lazy">
                                @else
                                    <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-white/60 bg-brand-accent text-lg font-bold text-white" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::of($testimonial->name)->trim()->substr(0, 1)->upper() }}
                                    </span>
                                @endif
                                <span>
                                    <span class="block font-semibold text-white">{{ $testimonial->name }}</span>
                                    @if ($testimonial->designation)
                                        <span class="block text-sm text-white/65">{{ $testimonial->designation }}</span>
                                    @endif
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>

            @if ($testimonials->count() > 1)
                <div class="mt-8 flex justify-center gap-2.5">
                    @foreach ($testimonials as $testimonial)
                        <button
                            type="button"
                            data-testimonial-dot
                            aria-label="Show testimonial {{ $loop->iteration }}"
                            @if ($loop->first) aria-current="true" @endif
                            @class([
                                'h-2.5 w-2.5 rounded-full border border-white transition',
                                'bg-white' => $loop->first,
                                'bg-transparent' => ! $loop->first,
                            ])
                        ></button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
