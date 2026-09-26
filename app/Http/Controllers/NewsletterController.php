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
        $success = "You're subscribed! Check your inbox for a confirmation email.";

        // Honeypot (standing rule for every public form, same pattern as
        // Contact Us): a bot that fills the hidden field gets the normal
        // success message, but nothing is saved or emailed.
        if (filled($request->input('hp_website'))) {
            return redirect($target)->with('newsletter_status', $success);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect($target)->withErrors($validator)->withInput();
        }

        $action->handle($validator->validated()['email']);

        return redirect($target)->with('newsletter_status', $success);
    }
}
