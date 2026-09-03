<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitContactRequestAction;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ADMIN-010 — the public Contact Us page: site-wide chrome + admin-
 * configured contact info (Website Setup's Contact tab) plus a submission
 * form. Spam protection is two-layered: a honeypot field (`hp_website`,
 * hidden from real visitors via CSS — see resources/views/contact/index.blade.php)
 * silently discards the request without saving anything or sending any
 * email, and the POST route is throttled (see routes/web.php).
 */
class ContactController extends Controller
{
    public function index(SettingsRepository $settings): View
    {
        $general = $settings->all('general');

        $siteName = ($general['site_name'] ?? null) ?: config('app.name');
        $tagline = $general['tagline'] ?? null;
        $logoMediaId = $general['logo_media_id'] ?? null;
        $logo = $logoMediaId ? Media::find($logoMediaId) : null;

        $contactEmail = $settings->get('contact', 'email');
        $contactAddress = $settings->get('contact', 'address');

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
        ]);
    }

    public function store(Request $request, SubmitContactRequestAction $action): RedirectResponse
    {
        // A real visitor never sees or fills in this field (it's visually
        // hidden — see the view). A bot that blindly fills every input
        // trips it, and the request is discarded silently: same redirect/
        // success message as a genuine submission, so the bot gets no
        // signal that it was caught.
        if (filled($request->input('hp_website'))) {
            return redirect()->route('contact.index')
                ->with('status', 'Thank you — your message has been received.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('contact.index')->withErrors($validator)->withInput();
        }

        $action->handle($validator->validated(), $request->ip(), $request->userAgent());

        return redirect()->route('contact.index')
            ->with('status', 'Thank you — your message has been received.');
    }
}
