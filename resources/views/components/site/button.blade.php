{{--
    WEB-101 design system — the one button style public pages use, so a
    "primary" action always looks the same whether it comes from a Hero
    section, a CTA section, or a plain form submit. `href` renders an <a>,
    its absence renders a <button> (defaulting to type="button", override
    via the `type` prop for a submit button).
--}}
@props(['href' => null, 'variant' => 'primary', 'type' => 'button'])

@php
    $variantClasses = match ($variant) {
        'outline' => 'border border-brand-navy bg-transparent text-brand-navy hover:bg-brand-navy hover:text-white',
        'secondary' => 'border border-brand-navy/20 bg-white text-brand-navy hover:border-brand-gold hover:text-brand-gold',
        default => 'bg-brand-gold text-white hover:bg-brand-gold-light',
    };

    $baseClasses = 'inline-flex items-center justify-center gap-1.5 rounded-md px-5 py-2.5 text-sm font-semibold '
        .'transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '
        .'focus-visible:outline-brand-navy '.$variantClasses;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$baseClasses]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$baseClasses]) }}>{{ $slot }}</button>
@endif
