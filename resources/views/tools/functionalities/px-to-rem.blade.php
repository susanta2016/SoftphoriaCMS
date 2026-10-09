{{--
    PX to REM Converter (App\Tools\Functionalities\PxToRem) — interface only;
    behaviour in resources/js/tools/px-to-rem.js, maths in
    resources/js/tools/px-to-rem/src/convert.js. Two-way conversion against a
    chosen root font size and number of decimal places, a reference table
    that follows both, and a list converter. Labels are not headings, so the
    page outline starts with the content sections below the tool.

    Without JavaScript the reference table still shows the 16px values and
    the formulas are explained; the list converter stays hidden.
--}}
@php
    $sizes = [1, 2, 4, 8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 40, 48, 64, 80, 96];
    $field = 'w-full rounded-xl border-0 bg-transparent px-4 py-3 text-brand-navy focus:ring-0 focus:outline-none';
    $box = 'mt-2 flex items-center rounded-xl border border-brand-navy/15 bg-white focus-within:border-brand-accent focus-within:ring-2 focus-within:ring-brand-accent/30';
@endphp

<div data-px-rem class="grid gap-8 lg:grid-cols-5">
    <form class="space-y-6 lg:col-span-3" novalidate data-px-rem-form>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="pxrem-base" class="block text-sm font-semibold text-brand-navy">Root font size</label>
                <div class="{{ $box }}">
                    <input id="pxrem-base" type="number" inputmode="decimal" min="1" max="200" step="any" value="16"
                           aria-describedby="pxrem-base-help pxrem-base-error"
                           class="{{ $field }} text-base" data-px-rem-base>
                    <span class="pe-4 text-sm font-medium text-brand-navy/50">px</span>
                </div>
                <p id="pxrem-base-help" class="mt-1.5 text-sm text-brand-navy/60">Browsers use 16px unless the site's CSS changes the <code>html</code> font size.</p>
                <p id="pxrem-base-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-px-rem-error="base"></p>
            </div>
            <div>
                <label for="pxrem-precision" class="block text-sm font-semibold text-brand-navy">Decimal places</label>
                <select id="pxrem-precision" aria-describedby="pxrem-precision-help"
                        class="mt-2 w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 text-base text-brand-navy focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none" data-px-rem-precision>
                    @foreach ([2, 3, 4, 5] as $places)
                        <option value="{{ $places }}" @selected($places === 4)>{{ $places }}</option>
                    @endforeach
                </select>
                <p id="pxrem-precision-help" class="mt-1.5 text-sm text-brand-navy/60">Results are rounded; trailing zeros are dropped.</p>
            </div>
        </div>

        <div class="grid items-start gap-4 sm:grid-cols-[1fr_auto_1fr]">
            <div>
                <label for="pxrem-px" class="block text-sm font-semibold text-brand-navy">Pixels</label>
                <div class="{{ $box }}">
                    <input id="pxrem-px" type="number" inputmode="decimal" step="any" value="24" aria-describedby="pxrem-px-error"
                           class="{{ $field }} text-lg font-semibold" data-px-rem-px>
                    <span class="pe-4 text-sm font-medium text-brand-navy/50">px</span>
                </div>
                <p id="pxrem-px-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-px-rem-error="px"></p>
            </div>
            <span class="mt-7 hidden h-12 items-center justify-center text-brand-navy/40 sm:flex" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path d="M7 7h11l-3-3M17 17H6l3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div>
                <label for="pxrem-rem" class="block text-sm font-semibold text-brand-navy">REM</label>
                <div class="{{ $box }}">
                    <input id="pxrem-rem" type="number" inputmode="decimal" step="any" value="1.5" aria-describedby="pxrem-rem-error"
                           class="{{ $field }} text-lg font-semibold" data-px-rem-rem>
                    <span class="pe-4 text-sm font-medium text-brand-navy/50">rem</span>
                </div>
                <p id="pxrem-rem-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-px-rem-error="rem"></p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl bg-brand-sky/70 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Result</p>
                <p class="mt-1 text-2xl font-bold break-all text-brand-navy" data-px-rem-result>24px = 1.5rem</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none" data-px-rem-copy>
                    Copy rem value
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-navy/15 bg-white px-5 py-3 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none" data-px-rem-copy-px>
                    Copy px value
                </button>
            </div>
        </div>
        <p class="sr-only" aria-live="polite" data-px-rem-live></p>
        <noscript><p class="text-sm text-brand-navy/70">This converter needs JavaScript. Without it: rem = px ÷ root font size (24 ÷ 16 = 1.5rem), and px = rem × root font size (1.5 × 16 = 24px). The table shows common values at the default 16px root.</p></noscript>
    </form>

    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-brand-navy/10">
            <table class="w-full text-sm">
                <caption class="bg-brand-mist px-4 py-3 text-left text-sm font-semibold text-brand-navy">
                    Reference at <span data-px-rem-base-label>16</span>px root
                </caption>
                <thead class="sr-only">
                    <tr><th scope="col">Pixels</th><th scope="col">REM</th></tr>
                </thead>
                <tbody class="divide-y divide-brand-navy/8" data-px-rem-table>
                    @foreach ($sizes as $size)
                        <tr class="odd:bg-white even:bg-brand-mist/40">
                            <th scope="row" class="px-4 py-1.5 text-left font-medium text-brand-navy/70">{{ $size }}px</th>
                            <td class="px-4 py-1.5 text-right font-semibold text-brand-navy" data-px="{{ $size }}">{{ rtrim(rtrim(number_format($size / 16, 4, '.', ''), '0'), '.') }}rem</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- List converter: shown by the script, since it cannot work without it --}}
    <div class="space-y-4 border-t border-brand-navy/10 pt-8 lg:col-span-5" hidden data-px-rem-bulk>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-lg font-bold text-brand-navy">Convert a list of values</p>
                <p class="mt-1 text-sm text-brand-navy/60">Paste values separated by new lines, commas or spaces, such as a CSS shorthand like 8px 16px. Uses the root font size and decimal places above.</p>
            </div>
            <fieldset class="flex shrink-0 gap-2">
                <legend class="sr-only">Conversion direction</legend>
                @foreach (['px-rem' => 'PX → REM', 'rem-px' => 'REM → PX'] as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="pxrem-direction" value="{{ $value }}" class="peer sr-only" @checked($value === 'px-rem') data-px-rem-direction>
                        <span class="inline-flex rounded-xl border border-brand-navy/15 bg-white px-4 py-2 text-sm font-semibold text-brand-navy transition peer-checked:border-brand-accent peer-checked:bg-brand-accent peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent peer-focus-visible:ring-offset-2">{{ $label }}</span>
                    </label>
                @endforeach
            </fieldset>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <label for="pxrem-list" class="block text-sm font-semibold text-brand-navy">Values</label>
                <textarea id="pxrem-list" rows="6" spellcheck="false" autocomplete="off" placeholder="8px 16px&#10;12px, 24px&#10;32"
                          aria-describedby="pxrem-list-summary"
                          class="mt-2 w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 font-mono text-sm text-brand-navy placeholder:text-brand-navy/35 focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none" data-px-rem-list></textarea>
            </div>
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p id="pxrem-results-label" class="text-sm font-semibold text-brand-navy">Results</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" disabled class="inline-flex items-center justify-center rounded-xl bg-brand-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50" data-px-rem-copy-all>
                            Copy all
                        </button>
                        <button type="button" disabled aria-describedby="pxrem-css-help" class="inline-flex items-center justify-center rounded-xl border border-brand-navy/15 bg-white px-4 py-2 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50" data-px-rem-copy-css>
                            Copy as CSS value
                        </button>
                    </div>
                </div>
                <ol aria-labelledby="pxrem-results-label" class="mt-2 max-h-64 space-y-1.5 overflow-y-auto rounded-xl bg-brand-mist/60 p-2 empty:hidden" data-px-rem-results></ol>
                <p id="pxrem-list-summary" class="mt-2 text-sm text-brand-navy/60" data-px-rem-summary>Results appear here as you type.</p>
                <p id="pxrem-css-help" class="mt-1 text-sm text-brand-navy/60">Copy all puts one value per line. Copy as CSS value joins them with spaces, ready for a property such as <code>padding</code>.</p>
            </div>
        </div>
    </div>
</div>
