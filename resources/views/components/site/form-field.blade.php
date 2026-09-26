{{--
    WEB-101 design system — one labeled form control (input/textarea/select)
    with a consistent focus/error style, error message and `old()`
    repopulation wired in. For type="select", pass the <option> tags as the
    slot (the caller applies its own @selected(old(...)) per option, same as
    before this component existed). Extra attributes (maxlength,
    autocomplete, placeholder…) pass straight through to the control.
--}}
@props(['name', 'label', 'type' => 'text', 'required' => false, 'rows' => 4, 'hint' => null])

@php
    $hasError = $errors->has($name);
    $fieldClasses = [
        'mt-1.5 block w-full rounded-xl border bg-brand-mist px-4 py-3 text-sm text-brand-navy placeholder:text-brand-navy/40 '
            .'transition focus:bg-white focus:outline-none focus:ring-4',
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $hasError,
        'border-brand-navy/10 hover:border-brand-navy/25 focus:border-brand-accent focus:ring-brand-accent/15' => ! $hasError,
    ];
    $describedBy = trim(($hint ? "{$name}-hint " : '').($hasError ? "{$name}-error" : ''));
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
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class($fieldClasses) }}
        >{{ old($name) }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class($fieldClasses) }}
        >{{ $slot }}</select>
    @else
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name) }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class($fieldClasses) }}
        >
    @endif

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1.5 text-xs text-brand-navy/50">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
