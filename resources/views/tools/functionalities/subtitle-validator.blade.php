{{--
    Subtitle Validator (App\Tools\Functionalities\SubtitleValidator) —
    interface only; behaviour in resources/js/tools/subtitle-validator.js,
    rules in resources/js/tools/subtitle-validator/src. Files are read in
    the browser and never uploaded. Labels are not headings, so the page
    outline starts with the content sections below the tool.
--}}
@php
    $field = 'mt-2 w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 text-base text-brand-navy focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none';
    $primary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';
    $secondary = 'inline-flex items-center justify-center gap-2 rounded-xl border border-brand-navy/15 bg-white px-4 py-2.5 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';
    $settings = [
        ['maxCharsPerLine', 'Max characters per line', 42, 1, 200, '1'],
        ['maxLines', 'Max lines per cue', 2, 1, 10, '1'],
        ['maxCps', 'Max characters per second', 20, 1, 100, 'any'],
        ['minDuration', 'Min cue duration (s)', 0.8, 0, 60, 'any'],
        ['maxDuration', 'Max cue duration (s)', 7, 0.1, 600, 'any'],
        ['minGap', 'Min gap between cues (s, 0 = off)', 0, 0, 10, 'any'],
    ];
@endphp

<div data-sv class="space-y-8">
    <noscript>
        <p class="rounded-2xl bg-brand-mist p-5 text-sm text-brand-navy/80">This validator runs in your browser and needs JavaScript. Your subtitle file is never uploaded.</p>
    </noscript>

    {{-- Input --}}
    <form class="space-y-5" novalidate data-sv-form>
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-brand-navy/20 bg-brand-mist/50 px-5 py-8 text-center transition data-[drag=true]:border-brand-accent data-[drag=true]:bg-brand-sky" data-sv-drop>
            <input type="file" id="sv-file" accept=".srt,.vtt,.webvtt" class="peer sr-only" data-sv-file>
            <label for="sv-file" class="{{ $primary }} cursor-pointer peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent peer-focus-visible:ring-offset-2">Choose a subtitle file</label>
            <p class="text-sm text-brand-navy/70">or drag and drop an <strong>.srt</strong> or <strong>.vtt</strong> file here (up to 5 MiB). It is read in your browser and never uploaded.</p>
            <p class="text-sm font-medium text-brand-navy" data-sv-file-name hidden></p>
        </div>

        <div>
            <label for="sv-text" class="block text-sm font-semibold text-brand-navy">Or paste subtitle text</label>
            <textarea id="sv-text" rows="8" spellcheck="false" autocomplete="off" aria-describedby="sv-text-help"
                      placeholder="1&#10;00:00:01,000 --> 00:00:03,000&#10;Your first subtitle"
                      class="{{ $field }} font-mono text-sm" data-sv-text></textarea>
            <p id="sv-text-help" class="mt-1.5 text-sm text-brand-navy/60">The file is checked as soon as you choose it; pasted text is checked as you type.</p>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="sm:w-64">
                <label for="sv-format" class="block text-sm font-semibold text-brand-navy">Format</label>
                <select id="sv-format" aria-describedby="sv-detected" class="{{ $field }}" data-sv-format>
                    <option value="auto">Detect automatically</option>
                    <option value="srt">SRT (SubRip)</option>
                    <option value="vtt">WebVTT</option>
                </select>
                <p id="sv-detected" class="mt-1.5 text-sm text-brand-navy/60" data-sv-detected></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="{{ $secondary }}" data-sv-sample="valid">Load valid sample</button>
                <button type="button" class="{{ $secondary }}" data-sv-sample="broken">Load sample with errors</button>
                <button type="button" class="{{ $secondary }}" data-sv-clear>Clear</button>
            </div>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert" hidden data-sv-error>
            <p data-sv-error-text></p>
            <button type="button" class="mt-3 {{ $secondary }}" hidden data-sv-cp1252>Read it as Windows-1252 (Western European)</button>
        </div>

        <details class="group rounded-2xl border border-brand-navy/10 bg-white" data-sv-settings>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-5 py-4 text-sm font-semibold text-brand-navy focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                Rule settings
                <span class="text-xs font-normal text-brand-navy/60 group-open:hidden">42 characters · 2 lines · 20 CPS · 0.8–7 s</span>
            </summary>
            <div class="border-t border-brand-navy/10 px-5 py-5">
                <p class="text-sm text-brand-navy/70">These are general-purpose defaults, not the rules of any particular platform. Change them to match your style guide; the results update straight away.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($settings as [$key, $label, $value, $min, $max, $step])
                        <div>
                            <label for="sv-{{ $key }}" class="block text-sm font-semibold text-brand-navy">{{ $label }}</label>
                            <input id="sv-{{ $key }}" type="number" inputmode="decimal" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}"
                                   class="{{ $field }}" data-sv-setting="{{ $key }}" data-default="{{ $value }}">
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-sm font-medium text-red-700" hidden data-sv-settings-error></p>
                <button type="button" class="mt-4 {{ $secondary }}" data-sv-reset-settings>Reset to defaults</button>
            </div>
        </details>
    </form>

    <p class="sr-only" role="status" aria-live="polite" data-sv-live></p>

    {{-- Results --}}
    <section class="space-y-6 border-t border-brand-navy/10 pt-8" aria-label="Validation results" tabindex="-1" hidden data-sv-results>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-sv-summary></div>
        <p class="text-sm text-brand-navy/60">Score: 100, minus 10 for each error and 3 for each warning. It checks format and readability only — not whether the words match the audio, accessibility compliance or acceptance by any platform.</p>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <fieldset class="flex flex-wrap gap-2">
                <legend class="sr-only">Show findings</legend>
                @foreach (['all' => 'All', 'error' => 'Errors', 'warning' => 'Warnings'] as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="sv-filter" value="{{ $value }}" class="peer sr-only" @checked($value === 'all') data-sv-filter>
                        <span class="inline-flex rounded-xl border border-brand-navy/15 bg-white px-4 py-2 text-sm font-semibold text-brand-navy transition peer-checked:border-brand-accent peer-checked:bg-brand-accent peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent peer-focus-visible:ring-offset-2">{{ $label }} <span class="ms-1" data-sv-count="{{ $value }}">0</span></span>
                    </label>
                @endforeach
            </fieldset>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="{{ $secondary }}" data-sv-copy-report>Copy report</button>
                <button type="button" class="{{ $secondary }}" data-sv-report="csv">Download report (CSV)</button>
                <button type="button" class="{{ $secondary }}" data-sv-report="txt">Download report (TXT)</button>
            </div>
        </div>

        <ol class="space-y-3" aria-label="Findings" data-sv-findings></ol>
        <p class="rounded-2xl bg-emerald-50 p-5 text-sm font-medium text-emerald-900" hidden data-sv-clean>No problems found with the current rules.</p>
        <button type="button" class="{{ $secondary }}" hidden data-sv-more></button>
    </section>

    {{-- Repair --}}
    <section class="space-y-5 rounded-2xl border border-brand-navy/10 bg-brand-mist/50 p-5 sm:p-6" aria-label="Fix and download" hidden data-sv-repair>
        <div>
            <p class="text-lg font-bold text-brand-navy">Fix and download</p>
            <p class="mt-1 text-sm text-brand-navy/70">Creates a corrected copy; your original file is not changed. The copy is checked again before you download it.</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <p class="text-sm font-semibold text-brand-navy">Always applied</p>
                <p class="mt-1 text-sm text-brand-navy/60">These never change what is shown or when.</p>
                <ul class="mt-2 space-y-1 text-sm text-brand-navy/80" data-sv-safe></ul>
            </div>
            <fieldset>
                <legend class="text-sm font-semibold text-brand-navy">Optional fixes</legend>
                <p class="mt-1 text-sm text-brand-navy/60">Only applied if you tick them, because they change text, order or timing.</p>
                <div class="mt-2 space-y-2" data-sv-optional></div>
            </fieldset>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="sm:w-64">
                <label for="sv-output" class="block text-sm font-semibold text-brand-navy">Save as</label>
                <select id="sv-output" class="{{ $field }}" data-sv-output>
                    <option value="same">Same format</option>
                    <option value="srt">SRT (.srt)</option>
                    <option value="vtt">WebVTT (.vtt)</option>
                </select>
            </div>
            <button type="button" class="{{ $primary }}" data-sv-preview>Preview corrected file</button>
        </div>

        <div class="space-y-4" hidden data-sv-preview-area tabindex="-1">
            <div class="rounded-xl bg-white p-4 text-sm" data-sv-changes></div>
            <pre class="max-h-80 overflow-auto rounded-xl bg-brand-navy p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-white" aria-label="Corrected file preview" data-sv-output-text></pre>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="{{ $primary }}" data-sv-download>Download corrected subtitle</button>
                <button type="button" class="{{ $secondary }}" data-sv-copy-output>Copy corrected text</button>
            </div>
        </div>
    </section>
</div>
