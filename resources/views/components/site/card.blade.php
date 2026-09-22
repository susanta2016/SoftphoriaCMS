{{-- WEB-101 design system — the one generic card surface (used directly, and as the base look for x-site.portfolio-item). --}}
<div {{ $attributes->class(['rounded-lg border border-brand-navy/10 bg-white p-6 shadow-sm']) }}>
    {{ $slot }}
</div>
