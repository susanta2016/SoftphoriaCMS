<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitContactRequestAction;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Contact\ContactDetailMasker;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Shared\Support\Spam\FormTimeTrap;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * ADMIN-010 — the public Contact Us page: site-wide chrome + admin-
 * configured contact info (Website Setup's Contact tab) plus a submission
 * form. Spam protection is layered:
 *
 * - a honeypot field (`hp_website`, hidden from real visitors via CSS — see
 *   resources/views/components/site/contact-form.blade.php);
 * - a time trap (FormTimeTrap): a submission sent back faster than a human
 *   could type it, or with a missing/forged token, is dropped;
 * - both of those discard silently (same success response as a genuine
 *   submission, nothing saved, no email), so a bot gets no signal;
 * - stricter validation (no links in name/subject, a link cap in the
 *   message, a fixed category list);
 * - the POST routes are throttled (see routes/web.php).
 *
 * The site's own email/phone/WhatsApp are never rendered in full: pages
 * show a masked form and reveal() hands out the real value on demand (see
 * ContactDetailMasker).
 */
class ContactController extends Controller
{
    /** Must match the <option> values in resources/views/components/site/contact-form.blade.php. */
    public const CATEGORIES = ['general', 'project', 'support', 'feedback', 'other'];

    public function index(SettingsRepository $settings): View
    {
        $general = $settings->all('general');

        $siteName = ($general['site_name'] ?? null) ?: config('app.name');
        $tagline = $general['tagline'] ?? null;
        $logoMediaId = $general['logo_media_id'] ?? null;
        $logo = $logoMediaId ? Media::find($logoMediaId) : null;

        $contactEmail = $settings->get('contact', 'email');
        $contactAddress = $settings->get('contact', 'address');
        $contactPhone = $settings->get('contact', 'phone');
        $contactWhatsapp = $settings->get('contact', 'whatsapp');

        $seo = SeoTagBuilder::build(null, [
            'title' => "Contact Us — {$siteName}",
            'description' => "Get in touch with {$siteName}.",
            'canonical' => route('contact.index'),
            'type' => 'website',
        ], $general);

        return view('contact.index', [
            'seo' => $seo,
            'siteName' => $siteName,
            'tagline' => $tagline,
            'logo' => $logo,
            'contactEmail' => $contactEmail,
            'contactAddress' => $contactAddress,
            'contactPhone' => $contactPhone,
            'contactWhatsapp' => $contactWhatsapp,
        ]);
    }

    /**
     * Shared by the /contact page (a normal form post, answered with a
     * redirect) and the site-wide Contact Us widget (a fetch() post with
     * Accept: application/json, answered with JSON so it never reloads the
     * page — see resources/views/components/site/contact-widget.blade.php).
     */
    public function store(Request $request, SubmitContactRequestAction $action, FormTimeTrap $timeTrap): RedirectResponse|JsonResponse
    {
        $success = 'Thank you — your message has been received.';
        $silentSuccess = fn (): RedirectResponse|JsonResponse => $request->expectsJson()
            ? response()->json(['message' => $success])
            : redirect()->route('contact.index')->with('status', $success);

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
            'email' => ['required', 'string', 'email:rfc,filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]{5,30}$/'],
            'subject' => ['nullable', 'string', 'max:255', 'not_regex:/https?:\/\/|www\./i'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'message' => ['required', 'string', 'min:2', 'max:5000', $this->linkLimit(3)],
        ], [
            'name.not_regex' => 'Please enter just your name — no links or special characters.',
            'phone.regex' => 'Please enter a valid phone number.',
            'subject.not_regex' => 'Please leave links out of the subject.',
        ]);

        if ($validator->fails()) {
            // JSON callers get Laravel's usual 422 { message, errors } shape,
            // built here since bootstrap/app.php only renders JSON errors for api/*.
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

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

    /**
     * Hands out one of the site's own contact details in full, for the
     * "Reveal" buttons in resources/views/components/site/contact-info.blade.php.
     * Only answers a same-origin fetch() (CSRF + X-Requested-With) carrying
     * a valid FormTimeTrap token, and the route is throttled, so the value
     * never sits in the page source for scrapers to harvest.
     */
    public function reveal(Request $request, SettingsRepository $settings, FormTimeTrap $timeTrap): JsonResponse
    {
        $channel = $request->input('channel');

        abort_unless($request->ajax() && in_array($channel, ContactDetailMasker::CHANNELS, true), 404);

        if (! $timeTrap->passes($request->input(FormTimeTrap::FIELD), FormTimeTrap::REVEAL_MIN_SECONDS)) {
            return response()->json(['message' => 'Please try again in a moment.'], 422);
        }

        $value = $settings->get('contact', $channel);

        abort_if(blank($value), 404);

        return response()
            ->json(ContactDetailMasker::reveal($channel, (string) $value))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Scripted spam leans on links; a genuine enquiry rarely needs more than
     * a couple.
     */
    private function linkLimit(int $max): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($max): void {
            if (is_string($value) && preg_match_all('~https?://|www\.~i', $value) > $max) {
                $fail("Please include no more than {$max} links in your message.");
            }
        };
    }

    private function logDiscarded(Request $request, string $reason): void
    {
        Log::info('Contact submission discarded as spam', ['reason' => $reason, 'ip' => $request->ip()]);
    }
}
