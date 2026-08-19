{{-- A literal "<?xml ... ?>" prologue in this file's raw markup would be
     misparsed as a PHP open tag on any environment with short_open_tag On
     (this project's included) — echoing it from real PHP avoids that. --}}
@php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        <lastmod>{{ ($url['lastmod'] ?? now())->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
