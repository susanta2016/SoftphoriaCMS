{{--
    The Services call-to-action band (Services Settings → Call to action),
    used on the landing page and every service page. Renders nothing when
    the heading is empty.
--}}
@props(['settings'])

@if (filled($settings->get('cta_heading')))
    <section {{ $attributes->class(['relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark py-16 text-white sm:py-20']) }} aria-labelledby="services-cta-heading">
        <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>
        <div class="mx-auto flex max-w-6xl flex-col items-start gap-8 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <h2 id="services-cta-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $settings->get('cta_heading') }}</h2>
                @if ($settings->get('cta_text'))
                    <p class="mt-4 text-lg leading-relaxed text-white/75">{{ $settings->get('cta_text') }}</p>
                @endif
            </div>
            @if ($settings->get('cta_label') && $settings->get('cta_url'))
                <a href="{{ $settings->get('cta_url') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-white px-7 py-4 text-base font-semibold text-brand-navy shadow-lg transition hover:bg-brand-sky">
                    {{ $settings->get('cta_label') }} <x-site.arrow class="h-4 w-4"/>
                </a>
            @endif
        </div>
    </section>
@endif
