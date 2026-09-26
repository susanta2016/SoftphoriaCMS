{{--
    The call-to-action band at the end of a tool page (the tool's own CTA or
    Tools Settings' default, resolved in ToolController). Same look as the
    Services CTA. Renders nothing without a heading.
--}}
@props(['cta'])

@if (filled($cta['heading'] ?? null))
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark py-16 text-white sm:py-20" aria-labelledby="tool-cta-heading">
        <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>
        <div class="mx-auto flex max-w-6xl flex-col items-start gap-8 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <h2 id="tool-cta-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $cta['heading'] }}</h2>
                @if (filled($cta['text'] ?? null))
                    <p class="mt-4 text-lg leading-relaxed text-white/75">{{ $cta['text'] }}</p>
                @endif
            </div>
            @if (filled($cta['label'] ?? null) && filled($cta['url'] ?? null))
                <a href="{{ $cta['url'] }}" data-tool-cta="cta" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-white px-7 py-4 text-base font-semibold text-brand-navy shadow-lg transition hover:bg-brand-sky focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-navy focus-visible:outline-none">
                    {{ $cta['label'] }} <x-site.arrow class="h-4 w-4"/>
                </a>
            @endif
        </div>
    </section>
@endif
