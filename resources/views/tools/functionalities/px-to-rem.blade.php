{{--
    PX to REM Converter (App\Tools\Functionalities\PxToRem) — interface only;
    behaviour in resources/js/tools/px-to-rem.js. Two-way conversion against
    a chosen root font size, plus a reference table that follows it.
--}}
@php $sizes = [10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72, 96]; @endphp

<div data-px-rem class="grid gap-6 lg:grid-cols-5">
    <form class="space-y-6 lg:col-span-3" novalidate data-px-rem-form>
        <div>
            <label for="pxrem-base" class="block text-sm font-semibold text-brand-navy">Root font size</label>
            <div class="mt-2 flex max-w-xs items-center rounded-xl border border-brand-navy/15 bg-white focus-within:border-brand-accent focus-within:ring-2 focus-within:ring-brand-accent/30">
                <input id="pxrem-base" type="number" inputmode="decimal" min="1" max="200" step="any" value="16"
                       aria-describedby="pxrem-base-help pxrem-base-error"
                       class="w-full rounded-xl border-0 bg-transparent px-4 py-3 text-base text-brand-navy focus:ring-0 focus:outline-none" data-px-rem-base>
                <span class="pe-4 text-sm font-medium text-brand-navy/50">px</span>
            </div>
            <p id="pxrem-base-help" class="mt-1.5 text-sm text-brand-navy/60">Browsers use 16px unless the site's CSS changes the <code>html</code> font size.</p>
            <p id="pxrem-base-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-px-rem-error="base"></p>
        </div>

        <div class="grid items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
            <div>
                <label for="pxrem-px" class="block text-sm font-semibold text-brand-navy">Pixels</label>
                <div class="mt-2 flex items-center rounded-xl border border-brand-navy/15 bg-white focus-within:border-brand-accent focus-within:ring-2 focus-within:ring-brand-accent/30">
                    <input id="pxrem-px" type="number" inputmode="decimal" step="any" value="24" aria-describedby="pxrem-px-error"
                           class="w-full rounded-xl border-0 bg-transparent px-4 py-3 text-lg font-semibold text-brand-navy focus:ring-0 focus:outline-none" data-px-rem-px>
                    <span class="pe-4 text-sm font-medium text-brand-navy/50">px</span>
                </div>
                <p id="pxrem-px-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-px-rem-error="px"></p>
            </div>
            <span class="hidden h-12 items-center justify-center text-brand-navy/40 sm:flex" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path d="M7 7h11l-3-3M17 17H6l3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div>
                <label for="pxrem-rem" class="block text-sm font-semibold text-brand-navy">REM</label>
                <div class="mt-2 flex items-center rounded-xl border border-brand-navy/15 bg-white focus-within:border-brand-accent focus-within:ring-2 focus-within:ring-brand-accent/30">
                    <input id="pxrem-rem" type="number" inputmode="decimal" step="any" value="1.5" aria-describedby="pxrem-rem-error"
                           class="w-full rounded-xl border-0 bg-transparent px-4 py-3 text-lg font-semibold text-brand-navy focus:ring-0 focus:outline-none" data-px-rem-rem>
                    <span class="pe-4 text-sm font-medium text-brand-navy/50">rem</span>
                </div>
                <p id="pxrem-rem-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-px-rem-error="rem"></p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl bg-brand-sky/70 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Result</p>
                <p class="mt-1 text-2xl font-bold text-brand-navy" data-px-rem-result>24px = 1.5rem</p>
            </div>
            <button type="button" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none" data-px-rem-copy>
                Copy rem value
            </button>
        </div>
        <p class="sr-only" aria-live="polite" data-px-rem-live></p>
        <noscript><p class="text-sm text-brand-navy/70">This converter needs JavaScript. Without it: rem = px ÷ root font size (e.g. 24 ÷ 16 = 1.5rem).</p></noscript>
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
                            <th scope="row" class="px-4 py-2 text-left font-medium text-brand-navy/70">{{ $size }}px</th>
                            <td class="px-4 py-2 text-right font-semibold text-brand-navy" data-px="{{ $size }}">{{ rtrim(rtrim(number_format($size / 16, 4, '.', ''), '0'), '.') }}rem</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
