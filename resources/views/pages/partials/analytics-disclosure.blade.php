{{--
    "Analytics and marketing tools" — appended automatically to the Cookie
    Policy and Privacy Policy pages (pages/partials/legal.blade.php) from
    the tools switched on in Website Setup → Analytics & Tracking, so the
    policies always match what the site actually loads. Rendered as plain
    HTML so it flows into the page's table of contents.
--}}
@php
    $tools = app(\App\Shared\Support\Analytics\AnalyticsIntegrations::class)->active();
    $categoryLabels = \App\Shared\Support\Analytics\AnalyticsIntegrations::CATEGORY_LABELS;
@endphp

<h2>Analytics and marketing tools</h2>
@if ($tools === [])
    <p>We do not currently use any analytics or marketing tools on this Website, so no analytics, tracking or advertising cookies are set. If we introduce any, they will be listed here and will only run with your consent.</p>
@else
    <p>The tools below help us understand how the Website is used and how our advertising performs. None of them loads until you consent to its cookie category in the cookie banner, and you can withdraw consent at any time using the cookie preferences icon in the bottom-left corner of every page. When you withdraw consent, we stop loading the tool and remove its cookies from your browser where possible.</p>
    <table>
        <thead>
            <tr><th>Tool</th><th>Provider</th><th>Purpose</th><th>Cookie category</th><th>Cookies (duration)</th></tr>
        </thead>
        <tbody>
            @foreach ($tools as $tool)
                <tr>
                    <td>{{ $tool['label'] }}</td>
                    <td>
                        @if ($tool['privacy_url'])
                            <a href="{{ $tool['privacy_url'] }}" rel="noopener noreferrer nofollow" target="_blank">{{ $tool['provider'] }}</a>
                        @else
                            {{ $tool['provider'] }}
                        @endif
                    </td>
                    <td>{{ $tool['purpose'] }}</td>
                    <td>{{ $categoryLabels[$tool['category']] ?? $tool['category'] }}</td>
                    <td>{{ collect($tool['cookies'])->map(fn ($cookie) => "{$cookie[0]} ({$cookie[1]})")->implode(', ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>These providers may process data outside India, including in the United States, under their own privacy policies (linked above). Advertising platforms may combine this data with information they already hold about you, for example if you are signed in to their service.</p>
@endif
