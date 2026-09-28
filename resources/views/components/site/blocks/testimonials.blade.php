{{--
    WEB-103 — the Testimonials section type: every enabled row from the
    admin Testimonials resource, in sort order, as a one-at-a-time slider.
    The slides sit side by side in a horizontal track; JS (resources/js/app.js,
    [data-testimonial-slider]) slides it, wires the arrows/dots, and
    autoplays every `autoplay_seconds` in an endless loop (last → first
    keeps sliding the same way, no rewind), pausing on hover/focus and never
    autoplaying for prefers-reduced-motion. Without JS the first slide
    simply shows.

    WEB-103 polish: a deep-navy technical band (faint grid + soft blue glow)
    instead of a photo, so it reads differently from both the Expertise
    gradient and the mountain-photo closing CTA. The active dot is styled
    off its aria-current attribute, which the JS keeps in sync.
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

    // Seconds per slide (Pages → Home → Testimonials); 0 turns autoplay off.
    // Sections saved before this setting existed default to 6.
    $autoplaySeconds = $testimonials->count() > 1 ? max(0, (int) ($content['autoplay_seconds'] ?? 6)) : 0;
@endphp

@if ($testimonials->isNotEmpty())
    <section
        @if (!empty($content['anchor'])) id="{{ $content['anchor'] }}" @endif
        class="relative isolate scroll-mt-24 overflow-hidden bg-brand-navy py-16 sm:py-20 lg:py-24"
        data-testimonial-slider
        data-autoplay="{{ $autoplaySeconds }}"
    >
        <div class="tech-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-40 left-1/2 -z-10 h-80 w-[40rem] max-w-full -translate-x-1/2 rounded-full bg-brand-accent/20 blur-3xl" aria-hidden="true"></div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-6">
                <x-site.section-heading :eyebrow="$content['eyebrow'] ?? null" :heading="$content['heading'] ?? null" :dark="true"/>

                @if ($testimonials->count() > 1)
                    <div class="flex shrink-0 gap-2">
                        <button type="button" data-testimonial-prev aria-label="Previous testimonial" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/25 text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            <x-site.arrow class="h-4 w-4 rotate-180"/>
                        </button>
                        <button type="button" data-testimonial-next aria-label="Next testimonial" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/25 text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
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
            <div class="mx-auto mt-12 max-w-3xl overflow-hidden">
                <div data-testimonial-track class="flex transition-transform duration-700 ease-in-out motion-reduce:transition-none" aria-live="polite">
                    @foreach ($testimonials as $testimonial)
                        <figure data-testimonial-slide class="flex w-full shrink-0 flex-col items-center pb-1 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="currentColor" class="h-9 w-9 text-brand-accent-light/70" aria-hidden="true"><path d="M13 7C7.5 8.6 4 12.9 4 18.5V25h9v-9H8.4c.3-3.3 2.2-5.7 5.6-7L13 7Zm15 0c-5.5 1.6-9 5.9-9 11.5V25h9v-9h-4.6c.3-3.3 2.2-5.7 5.6-7L28 7Z"/></svg>
                            <blockquote class="mt-6 px-2 text-lg leading-relaxed font-medium text-white/95 sm:px-6 sm:text-xl sm:leading-relaxed">
                                {{ $testimonial->message }}
                            </blockquote>
                            <figcaption class="mt-8 flex items-center justify-center gap-3.5 text-left">
                                @if ($testimonial->avatar)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($testimonial->avatar->disk)->url($testimonial->avatar->path) }}" alt="" width="56" height="56" class="h-14 w-14 shrink-0 rounded-full object-cover ring-2 ring-white/70" loading="lazy">
                                @else
                                    <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-accent text-lg font-bold text-white ring-2 ring-white/40" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::of($testimonial->name)->trim()->substr(0, 1)->upper() }}
                                    </span>
                                @endif
                                <span>
                                    <span class="block text-base font-semibold text-white">{{ $testimonial->name }}</span>
                                    @if ($testimonial->designation)
                                        <span class="mt-0.5 block text-sm text-white/70">{{ $testimonial->designation }}</span>
                                    @endif
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>

            @if ($testimonials->count() > 1)
                {{-- Small visible dots with a 24px hit area (after:-inset-2.5); the active one stretches into a pill. --}}
                <div class="mt-10 flex justify-center gap-3">
                    @foreach ($testimonials as $testimonial)
                        <button
                            type="button"
                            data-testimonial-dot
                            aria-label="Show testimonial {{ $loop->iteration }}"
                            @if ($loop->first) aria-current="true" @endif
                            class="relative h-2 w-2 rounded-full bg-white/35 transition-all duration-300 after:absolute after:-inset-2.5 hover:bg-white/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white aria-[current=true]:w-6 aria-[current=true]:bg-brand-accent-light"
                        ></button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
