<?php

namespace App\Http\Controllers;

use App\Actions\Newsletter\SubscribeToNewsletterAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public newsletter signup (footer form). Thin per the project's controller
 * convention — validation only, the actual save + email send lives in
 * SubscribeToNewsletterAction.
 *
 * Spam protection: the same honeypot pattern as ContactController::store()
 * (`hp_website`, hidden from real visitors via CSS — see
 * resources/views/components/site/newsletter-form.blade.php). Project-wide
 * rule: every new public-facing submission form gets this same honeypot.
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

        // A real visitor never sees or fills in this field. A bot that
        // blindly fills every input trips it, and the request is discarded
        // silently: same redirect/success message as a genuine submission.
        if (filled($request->input('hp_website'))) {
            return redirect($target)->with('newsletter_status', "You're subscribed! Check your inbox for a confirmation email.");
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect($target)->withErrors($validator)->withInput();
        }

        $action->handle($validator->validated()['email']);

        return redirect($target)->with('newsletter_status', "You're subscribed! Check your inbox for a confirmation email.");
    }
}
