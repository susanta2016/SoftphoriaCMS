<?php

namespace App\Http\Controllers;

use App\Actions\Newsletter\ConfirmNewsletterSubscriptionAction;
use App\Actions\Newsletter\SubscribeToNewsletterAction;
use App\Enums\NewsletterSubscriptionOutcome;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public newsletter signup (footer form) and the double opt-in confirmation
 * link it emails out. Thin per the project's controller convention —
 * validation/messaging only, the actual save + email send lives in
 * SubscribeToNewsletterAction / ConfirmNewsletterSubscriptionAction.
 *
 * Spam protection on subscribe(): the same honeypot pattern as
 * ContactController::store() (`hp_website`, hidden from real visitors via
 * CSS — see resources/views/components/site/newsletter-form.blade.php),
 * plus route-level rate limiting (routes/web.php). Project-wide rule: every
 * new public-facing submission form gets this same honeypot.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request, SubscribeToNewsletterAction $action): RedirectResponse
    {
        // Redirects back to the footer's newsletter card specifically (not
        // just the previous page) so the success/error message is visible
        // immediately instead of landing the visitor back at the page top,
        // scrolled away from the form they just submitted.
        $target = url()->previous().'#newsletter-subscribe';

        $confirmationMessage = 'Almost there! Check your inbox and click the confirmation link to complete your subscription.';

        // A real visitor never sees or fills in this field. A bot that
        // blindly fills every input trips it, and the request is discarded
        // silently: same success message a genuine submission would get,
        // with nothing actually saved or emailed.
        if (filled($request->input('hp_website'))) {
            return redirect($target)->with('newsletter_status', $confirmationMessage);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect($target)->withErrors($validator)->withInput();
        }

        $outcome = $action->handle($validator->validated()['email']);

        $message = match ($outcome) {
            NewsletterSubscriptionOutcome::ConfirmationSent => $confirmationMessage,
            NewsletterSubscriptionOutcome::AlreadySubscribed => "You're already subscribed to our newsletter.",
            // Deliberately generic — never distinguishes bounced from
            // complained to the visitor (see ConfirmNewsletterSubscriptionAction's
            // docblock and docs/SES-SNS-SETUP.md).
            NewsletterSubscriptionOutcome::Suppressed => "We're unable to subscribe this email address right now. Please contact us if you believe this is a mistake.",
        };

        return redirect($target)->with('newsletter_status', $message);
    }

    public function confirm(string $token, ConfirmNewsletterSubscriptionAction $action): View
    {
        $subscriber = $action->handle($token);

        $settings = app(SettingsRepository::class);
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Newsletter Confirmation — {$chrome['siteName']}",
            'description' => 'Newsletter subscription confirmation.',
            'canonical' => url()->current(),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('newsletter.confirmed', [
            ...$chrome,
            'seo' => $seo,
            'confirmed' => $subscriber !== null,
        ]);
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
