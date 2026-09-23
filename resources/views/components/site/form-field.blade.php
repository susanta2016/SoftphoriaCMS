{{--
    WEB-101 design system — one labeled form control (input/textarea/select)
    with a consistent focus/error style, error message and `old()`
    repopulation wired in. For type="select", pass the <option> tags as the
    slot (the caller applies its own @selected(old(...)) per option, same as
    before this component existed).
--}}
@props(['name', 'label', 'type' => 'text', 'required' => false, 'rows' => 4])

@php
    $fieldClasses = [
        'mt-1.5 block w-full rounded-md border px-3 py-2.5 text-sm text-brand-navy placeholder:text-brand-navy/40 '
            .'focus:outline-none focus:ring-2 focus:ring-brand-navy',
        'border-red-400' => $errors->has($name),
        'border-brand-navy/20' => ! $errors->has($name),
    ];
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-brand-navy">
        {{ $label }}
        @if ($required)
            <span class="text-brand-accent" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            {{ $attributes->class($fieldClasses) }}
        >{{ old($name) }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->class($fieldClasses) }}
        >{{ $slot }}</select>
    @else
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name) }}"
            @if ($required) required @endif
            {{ $attributes->class($fieldClasses) }}
        >
    @endif

    @error($name)
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
