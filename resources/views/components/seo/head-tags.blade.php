{{--
    Renders every <head> SEO tag from a resolved SeoTagBuilder array — the
    <title> tag itself stays owned by the caller (avoids a duplicate <title>
    when this is dropped into an existing layout). See
    App\Shared\Support\Seo\SeoTagBuilder for what populates $seo and why
    this is the one place both the Home page and the generic CMS page view
    render these tags from, instead of two independent implementations.
--}}
@props(['seo'])

<meta name="robots" content="{{ $seo['robots'] }}">
@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
@endif
@if (! empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif

<link rel="canonical" href="{{ $seo['canonical'] }}">

<!-- Schema.org markup for Google+ -->
<meta itemprop="name" content="{{ $seo['title'] }}">
@if (! empty($seo['description']))
    <meta itemprop="description" content="{{ $seo['description'] }}">
@endif
@if (! empty($seo['og_image']))
    <meta itemprop="image" content="{{ $seo['og_image'] }}">
@endif

<!-- Twitter Card data -->
<meta name="twitter:card" content="{{ $seo['twitter_card'] }}">
@if (! empty($seo['twitter_site']))
    <meta name="twitter:site" content="{{ $seo['twitter_site'] }}">
@endif
<meta name="twitter:title" content="{{ $seo['twitter_title'] }}">
@if (! empty($seo['twitter_description']))
    <meta name="twitter:description" content="{{ $seo['twitter_description'] }}">
@endif
@if (! empty($seo['twitter_site']))
    <meta name="twitter:creator" content="{{ $seo['twitter_site'] }}">
@endif
@if (! empty($seo['twitter_image']))
    <meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
@endif

<!-- Open Graph data -->
<meta property="og:title" content="{{ $seo['og_title'] }}">
<meta property="og:type" content="{{ $seo['og_type'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@if (! empty($seo['og_image']))
    <meta property="og:image" content="{{ $seo['og_image'] }}">
@endif
@if (! empty($seo['og_description']))
    <meta property="og:description" content="{{ $seo['og_description'] }}">
@endif
<meta property="og:site_name" content="{{ $seo['site_name'] }}">
@if (! empty($seo['fb_app_id']))
    <meta property="fb:app_id" content="{{ $seo['fb_app_id'] }}">
@endif

@if (! empty($seo['structured_data']))
    <script type="application/ld+json">{!! json_encode($seo['structured_data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
