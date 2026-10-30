<?php

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Actions\Subscribers\UnsubscribeRecipientAction;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The unsubscribe link in every campaign email.
 *
 * GET only shows a confirmation page — it never unsubscribes, because mail
 * security scanners and link previews fetch every URL in a message. The
 * page's button POSTs back to the same URL; so does a mail client's
 * one-click unsubscribe (List-Unsubscribe-Post), which is why the POST is
 * CSRF-exempt (bootstrap/app.php): the unguessable token in the path is
 * the authorization. An unknown token gets the same neutral page as a
 * valid one would before confirming, so tokens can't be probed.
 */
class UnsubscribeController extends Controller
{
    public function show(string $token, SettingsRepository $settings): View
    {
        $recipient = $this->find($token);

        return view('newsletter-marketing::unsubscribe', [
            'siteName' => $this->siteName($settings),
            'valid' => $recipient !== null,
            'done' => $recipient?->unsubscribed_at !== null || $recipient?->subscriber?->status === 'unsubscribed',
            'token' => $token,
        ]);
    }

    public function store(string $token, Request $request, UnsubscribeRecipientAction $unsubscribe, SettingsRepository $settings): View|Response
    {
        $recipient = $this->find($token);
        $oneClick = $request->input('List-Unsubscribe') === 'One-Click';

        if ($recipient !== null) {
            $unsubscribe->handle($recipient, $oneClick ? 'one_click' : 'link');
        }

        if ($oneClick) {
            return response('', $recipient !== null ? 200 : 404);
        }

        return view('newsletter-marketing::unsubscribe', [
            'siteName' => $this->siteName($settings),
            'valid' => $recipient !== null,
            'done' => $recipient !== null,
            'token' => $token,
        ]);
    }

    private function find(string $token): ?NewsletterCampaignRecipient
    {
        return strlen($token) >= 32 && strlen($token) <= 64
            ? NewsletterCampaignRecipient::query()->where('token', $token)->first()
            : null;
    }

    private function siteName(SettingsRepository $settings): string
    {
        return (string) ($settings->get('general', 'site_name') ?: config('app.name'));
    }
}
