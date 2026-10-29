<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitContactFormAction;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Shared\Support\Spam\FormTimeTrap;
use App\Shared\Support\Spam\MaxLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * The public Contact Us page — an info section (email/address, admin-
 * configured via Settings' Contact tab) plus a submission form. Spam
 * protection (same layers as the develop branch's contact form):
 * - a honeypot field (`hp_website`, hidden from real visitors via CSS —
 *   see resources/views/contact/index.blade.php);
 * - validation that rejects links/markup in the name and more than 3 links
 *   in the message;
 * - a time trap (FormTimeTrap): a submission sent back faster than a human
 *   could fill the form in, or without a valid token, is a bot;
 * - the per-IP 'contact-submit' rate limiter (AppServiceProvider).
 * Honeypot and time-trap hits are discarded silently (same success
 * response, nothing saved or emailed) and logged with their IP. Genuine
 * submissions record the sender's IP and user agent for the admin panel.
 */
class ContactController extends Controller
{
    public function index(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $contact = $this->contactInfo($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Contact Us — {$chrome['siteName']}",
            'description' => "Get in touch with {$chrome['siteName']}.",
            'canonical' => route('contact.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('contact.index', [
            ...$chrome,
            'seo' => $seo,
            'contactEmail' => $contact['email'],
            'contactAddress' => $contact['address'],
        ]);
    }

    public function store(Request $request, SubmitContactFormAction $action, FormTimeTrap $timeTrap): RedirectResponse
    {
        $silentSuccess = fn (): RedirectResponse => redirect()->route('contact.index')
            ->with('status', 'Thank you — your message has been received.');

        // A real visitor never sees or fills in this field (it's visually
        // hidden — see the view). A bot that blindly fills every input
        // trips it, and the request is discarded silently: same redirect/
        // success message as a genuine submission, so the bot gets no
        // signal that it was caught.
        if (filled($request->input('hp_website'))) {
            $this->logDiscarded($request, 'honeypot');

            return $silentSuccess();
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'not_regex:/https?:\/\/|www\.|[<>]/i'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:5000', new MaxLinks(3)],
        ], [
            'name.not_regex' => 'Please enter just your name — no links or special characters.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('contact.index')->withErrors($validator)->withInput();
        }

        // Checked after validation on purpose: validation errors only tell a
        // bot what the form's own markup already declares, while a real
        // visitor who gets corrected can still resubmit straight away.
        if (! $timeTrap->passes($request->input(FormTimeTrap::FIELD), FormTimeTrap::SUBMIT_MIN_SECONDS)) {
            $this->logDiscarded($request, 'time-trap');

            return $silentSuccess();
        }

        $action->handle($validator->validated(), $request->ip(), $request->userAgent());

        return $silentSuccess();
    }

    private function logDiscarded(Request $request, string $reason): void
    {
        Log::info('Contact submission discarded as spam', ['reason' => $reason, 'ip' => $request->ip()]);
    }

    /**
     * @return array{email: ?string, address: ?string}
     */
    private function contactInfo(SettingsRepository $settings): array
    {
        return [
            'email' => $settings->get('contact', 'email'),
            'address' => $settings->get('contact', 'address'),
        ];
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
