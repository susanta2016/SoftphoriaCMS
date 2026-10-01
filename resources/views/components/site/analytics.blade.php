{{--
    Analytics & Tracking output (Website Setup → Analytics & Tracking), placed
    in the <head> of the public layout.

    Each switched-on tool's script sits inside an inert
    <template data-consent-scripts="{category}">; nothing inside it runs until
    resources/js/app.js sees consent for that category in the cookie_consent
    cookie. See AnalyticsIntegrations for the full consent rules (e.g. no
    cookie banner = nothing loads, admins excluded by default).
--}}
@php
    $analytics = app(\App\Shared\Support\Analytics\AnalyticsIntegrations::class);
@endphp

@if ($analytics->shouldEmitScripts())
    <script type="application/json" data-consent-cookie-map>{!! json_encode($analytics->cookiesToClear(), JSON_HEX_TAG) !!}</script>
    @foreach ($analytics->active() as $key => $tool)
        <template data-consent-scripts="{{ $tool['category'] }}" data-consent-tool="{{ $key }}">{!! $analytics->script($key, $tool['id']) !!}</template>
    @endforeach
@endif
