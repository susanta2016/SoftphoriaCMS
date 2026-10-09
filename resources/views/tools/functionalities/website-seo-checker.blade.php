{{--
    Website SEO/Metadata Pre-launch Checker (App\Tools\Functionalities\WebsiteSeoChecker).
    The form posts to tools.seo-checker.audit over fetch(); the server runs
    the audit (app/Tools/SeoChecker) and resources/js/tools/website-seo-checker.js
    renders the JSON report into the empty regions below, as text only.
    Labels are not headings, so the page outline starts with the content
    sections below the tool. Without JavaScript the form does nothing.
--}}
@php
    $field = 'mt-2 w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 text-base text-brand-navy focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none';
    $primary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60';
    $secondary = 'inline-flex items-center justify-center gap-2 rounded-xl border border-brand-navy/15 bg-white px-4 py-2.5 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<div data-seo class="space-y-8" data-contact-url="{{ route('contact.index') }}">
    <noscript>
        <p class="rounded-2xl bg-brand-mist p-5 text-sm text-brand-navy/80">This checker needs JavaScript to send the address and show the report.</p>
    </noscript>

    <form method="post" action="{{ $auditUrl }}" class="space-y-5" novalidate data-seo-form>
        @csrf
        <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
            <label for="seo-hp_website">Website</label>
            <input type="text" id="seo-hp_website" name="hp_website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="seo-url" class="block text-sm font-semibold text-brand-navy">Page address</label>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <input id="seo-url" name="url" type="url" inputmode="url" autocomplete="url" spellcheck="false" required maxlength="2048"
                       placeholder="https://www.example.com/" aria-describedby="seo-url-help seo-url-error"
                       class="{{ $field }} sm:flex-1" data-seo-url>
                <button type="submit" class="{{ $primary }} sm:mt-2 sm:py-3.5" data-seo-submit>Check Website</button>
            </div>
            <p id="seo-url-help" class="mt-1.5 text-sm text-brand-navy/60">A public http:// or https:// address. One page is checked, plus the site's robots.txt and sitemap.</p>
            <p id="seo-url-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-seo-field-error></p>
        </div>

        <fieldset>
            <legend class="text-sm font-semibold text-brand-navy">What are you checking?</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                @foreach (['live' => ['The live site', 'It should be found in search engines.'], 'staging' => ['A staging or pre-launch copy', 'It should stay hidden until launch.']] as $value => [$label, $hint])
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-brand-navy/15 bg-white p-4 transition has-[:checked]:border-brand-accent has-[:checked]:bg-brand-sky/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-accent">
                        <input type="radio" name="intent" value="{{ $value }}" class="mt-1 h-4 w-4 accent-brand-accent" @checked($value === 'live') data-seo-intent>
                        <span>
                            <span class="block text-sm font-semibold text-brand-navy">{{ $label }}</span>
                            <span class="block text-sm text-brand-navy/60">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <p class="text-sm text-brand-navy/60">
            Our server fetches the page the way a crawler would, without running JavaScript. The address and the report aren't stored, and you don't need to sign up.
            Results describe what the server sent at the time of the check — they can't tell you whether Google has indexed the page.
        </p>
    </form>

    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-900" role="alert" hidden data-seo-error>
        <p class="font-semibold" data-seo-error-title>The check couldn't be completed</p>
        <p class="mt-1" data-seo-error-text></p>
    </div>

    {{-- Progress (one request; the steps are what the server works through) --}}
    <div class="rounded-2xl border border-brand-navy/10 bg-brand-mist/60 p-5" hidden data-seo-progress>
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 shrink-0 animate-spin text-brand-accent motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <p class="text-sm font-semibold text-brand-navy" data-seo-progress-text>Checking…</p>
            <span class="ms-auto text-sm text-brand-navy/60 tabular-nums" data-seo-elapsed></span>
        </div>
        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-brand-navy/10" aria-hidden="true">
            <div class="seo-progress-bar h-full w-1/3 rounded-full bg-brand-accent"></div>
        </div>
        <p class="mt-3 text-sm text-brand-navy/60">Most checks take 5–20 seconds. Slow websites can take up to about 40.</p>
    </div>

    <p class="sr-only" role="status" aria-live="polite" data-seo-live></p>

    {{-- Report (filled by the script) --}}
    <section class="space-y-8 border-t border-brand-navy/10 pt-8 focus:outline-none" aria-label="Audit report" tabindex="-1" hidden data-seo-report>
        <div class="flex flex-col gap-1 text-sm text-brand-navy/70" data-seo-meta></div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,18rem)_1fr]">
            <div class="flex flex-col items-center justify-center rounded-2xl border border-brand-navy/10 bg-white p-6 text-center shadow-sm" data-seo-score-card>
                <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Checklist score</p>
                <p class="mt-2 text-6xl font-bold tracking-tight text-brand-navy tabular-nums" data-seo-score>—</p>
                <p class="text-sm text-brand-navy/60" data-seo-score-suffix>out of 100</p>
            </div>
            <div class="rounded-2xl p-6" data-seo-readiness>
                <p class="text-xl font-bold" data-seo-readiness-label></p>
                <p class="mt-1 text-sm leading-relaxed" data-seo-readiness-summary></p>
                <div class="mt-4 flex flex-wrap gap-2" data-seo-counts></div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Results by category" data-seo-categories></div>

        <div class="space-y-8" data-seo-groups></div>

        <div class="space-y-3" hidden data-seo-preview-wrap>
            <p class="text-lg font-bold text-brand-navy">Link preview</p>
            <p class="text-sm text-brand-navy/60">Built only from the tags found on the page; platforms each style previews a little differently.</p>
            <div class="max-w-lg overflow-hidden rounded-2xl border border-brand-navy/15 bg-white shadow-sm" data-seo-preview></div>
        </div>

        {{-- Contextual service call to action: copy chosen from the findings --}}
        <div class="flex flex-col gap-5 rounded-2xl bg-gradient-to-br from-brand-navy to-brand-accent-dark p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8" data-seo-cta>
            <div class="max-w-2xl">
                <p class="text-lg font-bold" data-seo-cta-heading></p>
                <p class="mt-2 text-sm leading-relaxed text-white/80" data-seo-cta-text></p>
            </div>
            <a href="{{ route('contact.index') }}" data-tool-cta="report" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-brand-navy transition hover:bg-brand-sky focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-navy focus-visible:outline-none" data-seo-cta-link></a>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl border border-brand-navy/10 bg-brand-mist/50 p-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-brand-navy/70">Keep the report or share it with your developer.</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="{{ $secondary }}" data-seo-copy>Copy report</button>
                <button type="button" class="{{ $secondary }}" data-seo-download>Download report (TXT)</button>
                <button type="button" class="{{ $secondary }}" data-seo-again>Check another page</button>
            </div>
        </div>

        <details class="rounded-2xl border border-brand-navy/10 bg-white">
            <summary class="cursor-pointer list-none rounded-2xl px-5 py-4 text-sm font-semibold text-brand-navy focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none [&::-webkit-details-marker]:hidden">How the score works</summary>
            <div class="space-y-2 border-t border-brand-navy/10 px-5 py-4 text-sm leading-relaxed text-brand-navy/70">
                <p>Every check has a weight — launch-critical checks such as noindex, robots.txt and the HTTP status weigh the most. A passed check earns its full weight, a warning half, a blocker nothing; checks that couldn't run are left out. The score is the share of the possible points, out of 100.</p>
                <p>If there is any launch blocker, the score is capped at {{ \App\Tools\SeoChecker\Scorer::BLOCKER_CAP }} and the result says "Not ready to launch", so a good score elsewhere can't hide it. When the page itself can't be read (an error, a block or not HTML) there is no score at all.</p>
                <p>This is a checklist score for these checks only. It isn't a Google score, it doesn't predict rankings, and a high score doesn't guarantee indexing.</p>
            </div>
        </details>
    </section>
</div>
