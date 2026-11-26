{{--
    Fancy Text Generator (App\Tools\Functionalities\FancyTextGenerator) —
    interface only; behaviour in resources/js/tools/fancy-text-generator.js,
    the Unicode engine in resources/js/tools/fancy-text-generator/src.
    Everything happens in the browser: the text is never sent anywhere.

    The style cards, category buttons and platform presets are built by the
    script from the engine's own lists (one source of truth). The symbol
    library below is rendered here so it is readable without JavaScript.
--}}
@php
    $button = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';
    $secondary = $button.' border border-brand-navy/15 bg-white text-brand-navy hover:border-brand-accent hover:text-brand-accent';
    $field = 'w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 text-base text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none';
@endphp

<div data-ft class="space-y-10">
    <div class="space-y-5">
        <form data-ft-form novalidate>
            <div class="flex flex-wrap items-end justify-between gap-2">
                <label for="ft-input" class="text-sm font-semibold text-brand-navy">Your text</label>
                <p id="ft-count" class="text-sm text-brand-navy/60 tabular-nums" data-ft-count>0 characters</p>
            </div>
            <textarea id="ft-input" rows="3" maxlength="2000" spellcheck="false" autocomplete="off" autocapitalize="off"
                      placeholder="Type or paste your text here..." aria-describedby="ft-count ft-privacy"
                      class="{{ $field }} mt-2 resize-y text-lg" data-ft-input></textarea>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p id="ft-privacy" class="text-sm text-brand-navy/60">Converted in your browser. Your text is never uploaded or saved.</p>
                <div class="grid grid-cols-3 gap-2 sm:flex sm:shrink-0">
                    <button type="button" class="{{ $secondary }}" data-ft-random>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>Random<span class="sr-only sm:not-sr-only"> style</span></span>
                    </button>
                    <button type="button" class="{{ $button }} bg-brand-accent text-white hover:bg-brand-accent-dark" data-ft-copy-all disabled>Copy all</button>
                    <button type="button" class="{{ $secondary }}" data-ft-clear>Clear</button>
                </div>
            </div>
        </form>

        {{-- Random pick --}}
        <div class="rounded-2xl border border-brand-accent/30 bg-brand-sky/60 p-4 sm:p-5" hidden data-ft-spotlight tabindex="-1">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/60 uppercase">Random pick: <span data-ft-spotlight-name></span></p>
                    <p class="mt-1 text-2xl break-words whitespace-pre-wrap text-brand-navy" data-ft-spotlight-output></p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="{{ $button }} bg-brand-accent text-white hover:bg-brand-accent-dark" data-ft-spotlight-copy>Copy</button>
                    <button type="button" class="{{ $secondary }}" data-ft-random-again>Another</button>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="ft-search" class="sr-only">Search styles</label>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-brand-navy/40" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
                    <input id="ft-search" type="search" autocomplete="off" placeholder="Search styles: bold, cursive, tiny…" class="{{ $field }} ps-11" data-ft-search>
                </div>
            </div>
            <div>
                <label for="ft-preset" class="sr-only">Platform preset</label>
                <select id="ft-preset" class="{{ $field }}" data-ft-preset>
                    <option value="">Suggestions for a platform…</option>
                </select>
            </div>
        </div>

        <p class="rounded-xl border-l-4 border-brand-accent bg-brand-mist px-4 py-3 text-sm text-brand-navy/80" hidden data-ft-preset-note></p>

        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 sm:flex-wrap sm:overflow-visible" role="group" aria-label="Style categories" data-ft-categories></div>

        <p class="text-sm text-brand-navy/60" data-ft-status></p>

        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Text styles" data-ft-grid></ul>

        <p class="rounded-2xl bg-brand-mist p-6 text-center text-sm text-brand-navy/70" hidden data-ft-empty></p>

        <p class="flex gap-2 text-sm text-brand-navy/60">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Some social networks, games and devices filter, normalise or cannot display certain Unicode characters, so check how your text looks after you post it. Screen readers may read styled text letter by letter or skip it.</span>
        </p>

        <noscript>
            <p class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-950">The text converter needs JavaScript. The symbols below can still be selected and copied by hand.</p>
        </noscript>
    </div>

    {{-- Symbol library --}}
    <section class="border-t border-brand-navy/10 pt-8" aria-labelledby="ft-symbols-heading" data-ft-symbols>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="ft-symbols-heading" class="text-2xl font-bold tracking-tight text-brand-navy">Decorative Symbols to Copy and Paste</h2>
                <p class="mt-1 text-sm text-brand-navy/60">Select a symbol to copy it.</p>
            </div>
            <div class="sm:w-72">
                <label for="ft-symbol-search" class="sr-only">Search symbols</label>
                <input id="ft-symbol-search" type="search" autocomplete="off" placeholder="Search symbols: heart, arrow…" class="{{ $field }}" data-ft-symbol-search>
            </div>
        </div>

        <div class="mt-6 grid gap-8 lg:grid-cols-2">
            @foreach ($symbolGroups as $groupId => [$label, $symbols])
                <div data-ft-symbol-group="{{ $groupId }}" data-label="{{ mb_strtolower($label) }}">
                    <h3 class="text-sm font-semibold tracking-[0.12em] text-brand-navy/60 uppercase">{{ $label }}</h3>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($symbols as [$symbol, $name])
                            <li data-ft-symbol-item data-name="{{ mb_strtolower($name) }}">
                                <button type="button" title="{{ $name }}" aria-label="Copy {{ $name }}: {{ $symbol }}"
                                        class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-brand-navy/10 bg-white px-3 text-xl text-brand-navy transition hover:border-brand-accent hover:bg-brand-sky focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none data-[copied=true]:border-emerald-500 data-[copied=true]:bg-emerald-50"
                                        data-ft-symbol="{{ $symbol }}">{{ $symbol }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="mt-6 rounded-2xl bg-brand-mist p-6 text-center text-sm text-brand-navy/70" hidden data-ft-symbol-empty>No symbols match your search.</p>
    </section>

    <p class="sr-only" aria-live="polite" data-ft-live></p>
</div>
