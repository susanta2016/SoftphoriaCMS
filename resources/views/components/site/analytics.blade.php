{{--
    Analytics & Tracking output (Website Setup → Analytics & Tracking).

    head: search-engine verification meta tags (no cookies, always output),
          then each switched-on tool's script inside an inert
          <template data-consent-scripts="{category}">.
    body: the admin's custom end-of-body code, same template treatment.

    Nothing inside a template runs until resources/js/app.js sees consent for
    that category in the cookie_consent cookie — see AnalyticsIntegrations
    for the full consent rules (e.g. no banner = nothing loads).
--}}
@props(['position' => 'head'])

@php
    $analytics = app(\App\Shared\Support\Analytics\AnalyticsIntegrations::class);
    $settings = $analytics->settings();
    $emit = $analytics->shouldEmitScripts();
    $customCategory = $analytics->active()['custom']['category'] ?? 'tracking';
@endphp

@if ($position === 'head')
    @if (filled($settings->get('google_site_verification')))
        <meta name="google-site-verification" content="{{ $settings->get('google_site_verification') }}">
    @endif
    @if (filled($settings->get('bing_site_verification')))
        <meta name="msvalidate.01" content="{{ $settings->get('bing_site_verification') }}">
    @endif

    @if ($emit)
        <script type="application/json" data-consent-cookie-map>{!! json_encode($analytics->cookiesToClear(), JSON_HEX_TAG) !!}</script>
        @foreach ($analytics->active() as $key => $tool)
            @continue($key === 'custom')
            <template data-consent-scripts="{{ $tool['category'] }}" data-consent-tool="{{ $key }}">{!! $analytics->script($key, $tool['id']) !!}</template>
        @endforeach
        @if (filled($settings->get('custom_head_code')))
            <template data-consent-scripts="{{ $customCategory }}" data-consent-tool="custom-head">{!! $settings->get('custom_head_code') !!}</template>
        @endif
    @endif
@elseif ($emit && filled($settings->get('custom_body_code')))
    <template data-consent-scripts="{{ $customCategory }}" data-consent-tool="custom-body" data-consent-target="body">{!! $settings->get('custom_body_code') !!}</template>
@endif
