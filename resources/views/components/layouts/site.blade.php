@props(['seo'])

@php
    $favicon = null;
    $faviconMediaId = app(\App\Shared\Services\Settings\SettingsRepository::class)->get('general', 'favicon_media_id');
    $faviconMedia = $faviconMediaId ? \App\Models\Media::find($faviconMediaId) : null;
    if ($faviconMedia) {
        $favicon = \Illuminate\Support\Facades\Storage::disk($faviconMedia->disk)->url($faviconMedia->path);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $seo['title'] }}</title>
    <x-seo.head-tags :seo="$seo"/>
    {{ $head ?? '' }}

    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif

    @fonts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="flex min-h-screen flex-col overflow-x-hidden bg-white font-sans text-brand-navy antialiased">
    {{ $slot }}

    <x-site.back-to-top/>
    {{-- The /contact page already shows the full form. --}}
    @if (! request()->routeIs('contact.index') && app(\App\Shared\Support\Features\Features::class)->enabled('contact_widget'))
        <x-site.contact-widget/>
    @endif
    {{-- Contact-page call-to-action links open this instead (Contact Popup feature). --}}
    @if (! request()->routeIs('contact.index') && app(\App\Shared\Support\Features\Features::class)->enabled('contact_popup'))
        <x-site.contact-modal/>
    @endif
    <x-site.cookie-consent/>
</body>
</html>
