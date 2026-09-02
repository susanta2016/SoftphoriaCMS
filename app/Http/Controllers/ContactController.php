<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitContactFormAction;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The public Contact Us page — an info section (email/address, admin-
 * configured via Settings' Contact tab) plus a submission form. Spam
 * protection is two-layered: a honeypot field (`hp_website`, hidden from
 * real visitors via CSS — see resources/views/contact/index.blade.php)
 * silently discards the request without saving anything or sending any
 * email, and the route itself is throttled the same as
 * inspirational-resources.submit.
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

    public function store(Request $request, SubmitContactFormAction $action): RedirectResponse
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
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('contact.index')->withErrors($validator)->withInput();
        }

        $action->handle($validator->validated());

        return redirect()->route('contact.index')
            ->with('status', 'Thank you — your message has been received.');
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
