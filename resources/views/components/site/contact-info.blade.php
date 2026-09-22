{{--
    WEB-102 — the site's contact details block (email/phone/WhatsApp/
    address), extracted from resources/views/contact/index.blade.php so the
    Homepage's Contact/Free Consultation section (resources/views/home.blade.php)
    can show the exact same real numbers instead of a second copy. Values
    come from the existing Website Setup → Contact settings (WEB-101 item F)
    — nothing here is homepage-specific.
--}}
@props(['email' => null, 'phone' => null, 'whatsapp' => null, 'address' => null])

@if ($email || $phone || $whatsapp || $address)
    <div {{ $attributes->class(['space-y-1.5 text-brand-navy/80']) }}>
        @if ($email)
            <p>Email: <a href="mailto:{{ $email }}" class="font-medium text-brand-navy underline decoration-brand-navy/30 underline-offset-2 hover:text-brand-gold">{{ $email }}</a></p>
        @endif
        @if ($phone)
            <p>Phone: <a href="tel:{{ $phone }}" class="font-medium text-brand-navy underline decoration-brand-navy/30 underline-offset-2 hover:text-brand-gold">{{ $phone }}</a></p>
        @endif
        @if ($whatsapp)
            <p>WhatsApp: <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="font-medium text-brand-navy underline decoration-brand-navy/30 underline-offset-2 hover:text-brand-gold">Chat on WhatsApp</a></p>
        @endif
        @if ($address)
            <p style="white-space: pre-line">Address: {{ $address }}</p>
        @endif
    </div>
@endif
