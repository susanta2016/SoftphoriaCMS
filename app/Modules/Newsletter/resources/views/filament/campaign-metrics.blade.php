{{--
    Campaign report tiles (ViewNewsletterCampaign). $report is an
    App\Modules\Newsletter\Support\CampaignReport; $tracking says whether
    opens/clicks were tracked for this campaign.
--}}
@php
    $tiles = [
        ['Eligible recipients', $report->get('total'), null, 'Recipients in the snapshot taken when sending started.'],
        ['Sent (submitted)', $report->get('submitted'), null, 'Accepted by the mail provider. Not proof of delivery.'],
        ['Delivered', $report->get('delivered'), $report->rate('delivered'), 'Confirmed by provider delivery events (SES). Stays 0 without them.'],
        ['Bounced', $report->get('bounced'), $report->rate('bounced'), 'Hard bounces. Those addresses are now suppressed.'],
        ['Failed', $report->get('failed'), null, 'Could not be sent after retries, or rejected by the provider.'],
        ['Skipped', $report->get('skipped'), null, 'No longer eligible at send time (unsubscribed or suppressed after scheduling).'],
        ['Unique opens', $tracking['opens'] ? $report->get('opened') : null, $tracking['opens'] ? $report->rate('opened') : null, 'Approximate: privacy features inflate and hide opens.'],
        ['Unique clicks', $tracking['clicks'] ? $report->get('clicked') : null, $tracking['clicks'] ? $report->rate('clicked') : null, 'Recipients who clicked at least one link.'],
        ['Unsubscribes', $report->get('unsubscribed'), $report->rate('unsubscribed'), null],
        ['Spam complaints', $report->get('complained'), $report->rate('complained'), 'Those addresses are now suppressed.'],
    ];
@endphp
@include('newsletter-marketing::filament.styles')
<div class="nl-admin">
    <div class="nl-tiles">
        @foreach ($tiles as [$label, $value, $rate, $hint])
            <div class="nl-tile" @if ($hint) title="{{ $hint }}" @endif>
                <div class="nl-tile-label">{{ $label }}</div>
                <div class="nl-tile-value">{{ $value === null ? 'Not tracked' : number_format($value) }}</div>
                @if ($rate !== null)
                    <div class="nl-tile-rate">{{ $rate }}% of sent</div>
                @endif
            </div>
        @endforeach
    </div>
    @if ($report->get('outstanding') > 0)
        <p class="nl-note">{{ number_format($report->get('outstanding')) }} recipient(s) still queued.</p>
    @endif
    <p class="nl-note">Hover a figure for what it counts. Opens are approximate.</p>
</div>
