{{--
    The "check before sending" summary in the Schedule / Send Now
    confirmation modals (NewsletterCampaignResource).
--}}
@include('newsletter-marketing::filament.styles')
<div class="nl-admin">
    <dl class="nl-dl">
        @foreach ($rows as $label => $value)
            <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
        @endforeach
    </dl>
    @if (! empty($problems))
        <div class="nl-alert" role="alert">
            <strong>This campaign can't be sent yet:</strong>
            <ul>
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
