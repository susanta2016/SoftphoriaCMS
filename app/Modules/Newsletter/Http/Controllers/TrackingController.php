<?php

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Actions\RecordNewsletterEventAction;
use App\Modules\Newsletter\Enums\NewsletterEventType;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Support\NewsletterFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Open pixel and click redirect for campaigns sent with tracking on.
 *
 * The click redirect only ever goes to a URL recorded in that campaign's
 * own tracked_links map, looked up by hash — never to a URL taken from
 * the request — so it can't be used as an open redirect. It keeps
 * redirecting with the module off (people's links must not break) but
 * records nothing then; the pixel likewise always returns the image.
 *
 * Opens are approximate: privacy features (Apple Mail Privacy Protection,
 * image proxies, blocked images) both inflate and hide them. Repeat hits
 * within a minute are counted once.
 */
class TrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $token, RecordNewsletterEventAction $record): Response
    {
        $recipient = $this->find($token);

        if ($recipient !== null && NewsletterFeature::enabled() && $recipient->campaign?->track_opens) {
            $record->handle($recipient, NewsletterEventType::Open, 'pixel', 'open|'.$recipient->id.'|'.now()->format('YmdHi'));
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function click(string $token, string $link, RecordNewsletterEventAction $record): RedirectResponse
    {
        $recipient = $this->find($token);
        $url = $recipient?->campaign?->tracked_links[$link] ?? null;

        abort_if(! is_string($url) || ! preg_match('#^https?://#i', $url), 404);

        if (NewsletterFeature::enabled() && $recipient->campaign->track_clicks) {
            $record->handle($recipient, NewsletterEventType::Click, 'redirect', 'click|'.$recipient->id.'|'.$link.'|'.now()->format('YmdHi'), detail: $url);
        }

        return redirect()->away($url);
    }

    private function find(string $token): ?NewsletterCampaignRecipient
    {
        return strlen($token) >= 32 && strlen($token) <= 64
            ? NewsletterCampaignRecipient::query()->with('campaign')->where('token', $token)->first()
            : null;
    }
}
