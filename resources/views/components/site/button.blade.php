{{--
    WEB-101 design system — the one button style public pages use, so a
    "primary" action always looks the same whether it comes from a Hero
    section, a CTA section, or a plain form submit. `href` renders an <a>,
    its absence renders a <button> (defaulting to type="button", override
    via the `type` prop for a submit button). WEB-103 added `outline-light`
    (for dark bands) and `size="lg"`; `light` is a solid white button for dark bands.
--}}
@props(['href' => null, 'variant' => 'primary', 'type' => 'button', 'size' => 'md'])

@php
    $variantClasses = match ($variant) {
        'outline' => 'border border-brand-navy bg-transparent text-brand-navy hover:bg-brand-navy hover:text-white',
        'outline-light' => 'border border-white/70 bg-transparent text-white hover:border-white hover:bg-white/10',
        'light' => 'border border-white bg-white text-brand-navy hover:border-brand-sky hover:bg-brand-sky',
        'secondary' => 'border border-brand-navy/20 bg-white text-brand-navy hover:border-brand-accent hover:text-brand-accent',
        default => 'border border-brand-accent bg-brand-accent text-white hover:border-brand-accent-dark hover:bg-brand-accent-dark',
    };

    $sizeClasses = $size === 'lg' ? 'px-6 py-3' : 'px-5 py-2.5';

    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-md text-sm font-semibold '
        .'transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '
        .'focus-visible:outline-brand-accent '.$sizeClasses.' '.$variantClasses;

    // A link to a frontend feature switched off in Features Activation
    // (e.g. "View Our Services" while Services is off) isn't rendered.
    $hidden = $href && app(\App\Shared\Support\Features\Features::class)->hidesLink($href);
@endphp

@if ($hidden)
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->class([$baseClasses]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$baseClasses]) }}>{{ $slot }}</button>
@endif
