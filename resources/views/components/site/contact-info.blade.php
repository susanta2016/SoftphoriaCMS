{{--
    WEB-102: the site's contact details block (email/phone/WhatsApp/
    address), shared by the Contact page (resources/views/contact/index.blade.php)
    and every CMS contact_form section (components/site/sections.blade.php).
    Values come from Website Setup → Contact settings.

    Anti-harvesting: the email, phone and WhatsApp number are never written
    into the HTML (no mailto:/tel:/wa.me hrefs either). Only a masked form
    is rendered (ContactDetailMasker); each "Reveal" button fetches the real
    value from route('contact.reveal') (CSRF + time trap + throttle) and
    resources/js/app.js ([data-contact-reveal-scope]) swaps in a link.
    Visitors without JavaScript still see the masked value and the form.
    The postal address is public business information and stays readable.
--}}
@props(['email' => null, 'phone' => null, 'whatsapp' => null, 'address' => null])

@php
    $masker = \App\Shared\Support\Contact\ContactDetailMasker::class;

    $channels = array_filter([
        'email' => $email ? ['label' => 'Email us', 'hint' => 'For project enquiries and general questions', 'masked' => $masker::mask('email', $email)] : null,
        'phone' => $phone ? ['label' => 'Call sales & support', 'hint' => 'Speak with our team directly', 'masked' => $masker::mask('phone', $phone)] : null,
        'whatsapp' => $whatsapp ? ['label' => 'WhatsApp', 'hint' => 'Message us for a quick reply', 'masked' => $masker::mask('whatsapp', $whatsapp)] : null,
    ]);
@endphp

@if ($channels || $address)
    <div
        data-contact-reveal-scope
        data-reveal-url="{{ route('contact.reveal') }}"
        data-reveal-token="{{ app(\App\Shared\Support\Spam\FormTimeTrap::class)->issue() }}"
        data-csrf="{{ csrf_token() }}"
        {{ $attributes->class(['grid gap-3']) }}
    >
        @foreach ($channels as $channel => $info)
            <div class="group flex items-start gap-4 rounded-2xl border border-brand-navy/10 bg-white p-4 shadow-sm transition hover:border-brand-accent/30 hover:shadow-md sm:p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-sky text-brand-accent transition group-hover:bg-brand-accent group-hover:text-white" aria-hidden="true">
                    @switch($channel)
                        @case('email')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @break
                        @case('phone')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M5 4h3.5l1.5 4.5-2 1.5a11 11 0 006 6l1.5-2 4.5 1.5V19a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z" stroke-linejoin="round"/></svg>
                            @break
                        @default
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M4 20l1.3-3.9A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4.1-1L4 20z" stroke-linejoin="round"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a4 4 0 01-2-2l.8-1-1-2L9 9.5z" stroke-linejoin="round"/></svg>
                    @endswitch
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-brand-navy">{{ $info['label'] }}</p>
                    <p class="mt-0.5 text-xs text-brand-navy/55">{{ $info['hint'] }}</p>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2" data-contact-reveal-slot="{{ $channel }}" aria-live="polite">
                        <span class="font-mono text-sm tracking-wide text-brand-navy/75 select-none" data-contact-reveal-masked>{{ $info['masked'] }}</span>
                        <button
                            type="button"
                            data-contact-reveal="{{ $channel }}"
                            class="inline-flex items-center gap-1.5 rounded-full border border-brand-accent/25 bg-brand-sky px-3 py-1 text-xs font-semibold text-brand-accent transition hover:border-brand-accent hover:bg-brand-accent hover:text-white focus-visible:ring-2 focus-visible:ring-brand-accent/40 focus-visible:outline-none disabled:cursor-wait disabled:opacity-60"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" stroke-linejoin="round"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Reveal<span class="sr-only"> {{ strtolower($info['label']) }} details</span></span>
                        </button>
                    </div>
                    <p class="mt-1.5 hidden text-xs text-red-600" data-contact-reveal-error></p>
                </div>
            </div>
        @endforeach

        @if ($address)
            <div class="flex items-start gap-4 rounded-2xl border border-brand-navy/10 bg-white p-4 shadow-sm sm:p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-sky text-brand-accent" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0114 0C19 14.8 12 21 12 21z" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-navy">Visit our office</p>
                    <address class="mt-1 text-sm leading-relaxed text-brand-navy/70 not-italic" style="white-space: pre-line">{{ $address }}</address>
                </div>
            </div>
        @endif
    </div>
@endif
