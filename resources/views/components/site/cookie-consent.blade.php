@php
    $settings = app(\App\Shared\Services\Settings\SettingsRepository::class);
    $enabled = $settings->get('cookies', 'enabled', true);
@endphp

@if ($enabled)
    @php
        $cookies = array_merge(config('cookies_policy'), $settings->all('cookies'));
        $paragraphs = fn (?string $text): array => array_filter(array_map('trim', preg_split('/\n\s*\n/', (string) $text)));

        $siteName = ($settings->get('general', 'site_name') ?? null) ?: 'All The Things Light';

        $privacyPolicyPage = \App\Models\Page::query()->published()->where('slug', 'privacy-policy')->first();

        $categories = [
            ['key' => 'privacy', 'label' => 'Your privacy', 'toggle' => null],
            ['key' => 'necessary', 'label' => 'Strictly necessary cookies', 'toggle' => 'always'],
            ['key' => 'functionality', 'label' => 'Functionality cookies', 'toggle' => 'optional'],
            ['key' => 'tracking', 'label' => 'Tracking cookies', 'toggle' => 'optional'],
            ['key' => 'targeting', 'label' => 'Targeting and advertising cookies', 'toggle' => 'optional'],
            ['key' => 'more-info', 'label' => 'More information', 'toggle' => null],
        ];
    @endphp

    {{-- Bottom consent banner — hidden by default, revealed by app.js only when no consent cookie is present yet. --}}
    <div data-cookie-banner class="fixed inset-x-0 bottom-0 z-50 hidden border-t border-brand-navy/10 bg-neutral-100 px-4 py-6 shadow-[0_-4px_20px_rgba(0,0,0,0.08)] sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-brand-navy">{{ $cookies['banner_title'] ?? 'We use cookies' }}</h2>
                <p class="mt-1 text-sm text-brand-navy/70">{{ $cookies['banner_description'] ?? '' }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <button type="button" data-cookie-agree class="rounded-md bg-brand-gold px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-gold-light">I agree</button>
                <button type="button" data-cookie-decline class="rounded-md border border-brand-navy/30 px-4 py-2 text-sm font-semibold text-brand-navy transition hover:bg-brand-navy/5">I decline</button>
                <button type="button" data-cookie-preferences-open class="rounded-md px-4 py-2 text-sm font-semibold text-brand-navy underline decoration-brand-navy/30 underline-offset-2 transition hover:text-brand-gold">Change my preferences</button>
            </div>
        </div>
    </div>

    {{-- Cookies Preferences Center modal — also hidden by default, opened via "Change my preferences" or the footer's "Cookie Settings" link. --}}
    <div data-cookie-preferences class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/60 p-4">
        <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-brand-navy/10 px-6 py-5">
                <div>
                    <p class="text-sm text-brand-navy/50">{{ $siteName }}</p>
                    <h2 class="text-xl font-bold text-brand-navy">Cookies Preferences Center</h2>
                </div>
                <button type="button" data-cookie-preferences-close aria-label="Close" class="text-brand-navy/50 transition hover:text-brand-navy">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="flex flex-1 flex-col overflow-hidden sm:flex-row">
                <div class="flex shrink-0 flex-row overflow-x-auto border-b border-brand-navy/10 bg-neutral-50 sm:w-64 sm:flex-col sm:overflow-x-visible sm:overflow-y-auto sm:border-r sm:border-b-0">
                    @foreach ($categories as $index => $category)
                        <button
                            type="button"
                            data-cookie-tab-trigger="{{ $category['key'] }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            class="cookie-tab-trigger shrink-0 border-b-2 border-transparent px-4 py-3 text-left text-sm font-medium transition hover:bg-brand-navy/5 sm:border-b-0 sm:border-l-2 {{ $index === 0 ? 'is-active bg-white font-semibold text-brand-navy sm:border-l-brand-gold' : 'text-brand-navy/70' }}"
                        >{{ $category['label'] }}</button>
                    @endforeach
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    @foreach ($categories as $index => $category)
                        <div data-cookie-tab-panel="{{ $category['key'] }}" class="cookie-tab-panel space-y-3 {{ $index === 0 ? '' : 'hidden' }}">
                            @if ($category['key'] === 'more-info')
                                <h3 class="text-lg font-bold text-brand-navy">{{ $cookies['more_info_title'] ?? 'More information' }}</h3>
                                <p class="text-sm text-brand-navy/75">{{ $cookies['more_info_description'] ?? '' }}</p>
                                <p class="text-sm text-brand-navy/75">
                                    @if ($privacyPolicyPage)
                                        To find out more, please visit our <a href="{{ route('pages.show', $privacyPolicyPage) }}" class="font-semibold text-brand-navy underline hover:text-brand-gold">Privacy Policy</a>.
                                    @else
                                        To find out more, please visit our Privacy Policy.
                                    @endif
                                </p>
                            @else
                                <h3 class="text-lg font-bold text-brand-navy">{{ $cookies["{$category['key']}_title"] ?? $category['label'] }}</h3>
                                @foreach ($paragraphs($cookies["{$category['key']}_description"] ?? '') as $paragraph)
                                    <p class="text-sm text-brand-navy/75">{{ $paragraph }}</p>
                                @endforeach

                                @if ($category['toggle'] === 'always')
                                    <label class="mt-2 inline-flex items-center gap-3">
                                        <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
                                            <input type="checkbox" checked disabled class="peer sr-only">
                                            <span class="absolute inset-0 rounded-full bg-emerald-600"></span>
                                            <span class="absolute left-0.5 h-5 w-5 translate-x-5 rounded-full bg-white shadow"></span>
                                        </span>
                                        <span class="text-sm text-brand-navy/70">Always active</span>
                                    </label>
                                @elseif ($category['toggle'] === 'optional')
                                    <label class="mt-2 inline-flex cursor-pointer items-center gap-3">
                                        <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
                                            <input type="checkbox" data-cookie-toggle="{{ $category['key'] }}" class="peer sr-only">
                                            <span class="absolute inset-0 rounded-full bg-brand-navy/20 transition peer-checked:bg-emerald-600"></span>
                                            <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                                        </span>
                                        <span data-cookie-toggle-label="{{ $category['key'] }}" class="text-sm text-brand-navy/70">Inactive</span>
                                    </label>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end border-t border-brand-navy/10 px-6 py-4">
                <button type="button" data-cookie-save class="rounded-md bg-brand-gold px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-gold-light">Save my preferences</button>
            </div>
        </div>
    </div>
@endif
