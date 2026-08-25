<?php

namespace App\Http\Controllers;

use App\Actions\Registration\RegisterFreeUserAction;
use App\Actions\Registration\RegisterProUserAction;
use App\Models\Media;
use App\Models\User;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ExceptionInterface;

/**
 * Public registration (points 1-6 of the confirmed spec) — one shared form,
 * two distinct server-side entry points (registerFree/registerPro) so the
 * server, never a submitted field, decides which flow runs. Thin per the
 * project's controller convention (NewsletterController): validation only,
 * the actual account/Checkout Session creation lives in the Registration
 * Actions.
 */
class RegistrationController extends Controller
{
    public function show(SettingsRepository $settings, GlobalPricingResolver $pricing): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Register — {$chrome['siteName']}",
            'description' => 'Create your free account, or become a Pro Member for full access.',
            'canonical' => route('register.show'),
            'type' => 'website',
        ], $chrome['general']);

        return view('register.show', [
            ...$chrome,
            'seo' => $seo,
            'proPrice' => $pricing->proMemberMonthlyPrice(),
            'cancellationNote' => $settings->get(
                'pricing',
                'pro_member_cancellation_note',
                'If you cancel, your Pro membership stays active until the end of your current billing period — you will not lose access immediately.',
            ),
        ]);
    }

    public function registerFree(Request $request, RegisterFreeUserAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...self::optionalProfileRules(),
        ]);

        if ($validator->fails()) {
            return redirect()->route('register.show')->withErrors($validator)->withInput();
        }

        $action->handle($validator->validated());

        return redirect()->route('register.free.thank-you');
    }

    public function freeThankYou(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Thank You — {$chrome['siteName']}",
            'description' => 'Registration confirmation.',
            'canonical' => route('register.free.thank-you'),
            'type' => 'website',
        ], $chrome['general']);

        return view('register.free-thank-you', [
            ...$chrome,
            'seo' => $seo,
            'message' => $settings->get(
                'registration',
                'free_confirmation_message',
                'Thank you for your registration! Please verify your registered email from your mailbox.',
            ),
        ]);
    }

    public function registerPro(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...self::optionalProfileRules(),
        ]);

        if ($validator->fails()) {
            return redirect()->route('register.show')->withErrors($validator)->withInput();
        }

        try {
            // Resolved here, inside the try, rather than as a typed method
            // parameter — Laravel builds a typed parameter (including its
            // own StripeClient dependency) before this method body runs,
            // which would let a Stripe SDK exception (e.g. a missing/empty
            // API key) escape uncaught.
            $outcome = app(RegisterProUserAction::class)->handle($validator->validated());
        } catch (ValidationException $exception) {
            return redirect()->route('register.show')->withErrors($exception->errors())->withInput();
        } catch (ExceptionInterface $exception) {
            Log::error('Stripe embedded Checkout Session creation failed', ['exception' => $exception->getMessage()]);

            return redirect()->route('register.show')
                ->withErrors(['email' => 'Payment setup is temporarily unavailable. Please try again shortly.'])
                ->withInput();
        }

        if ($outcome->alreadyPaidAwaitingVerification) {
            return redirect()->route('register.show')
                ->with('registration_notice', "You've already registered and completed payment — please check your email to verify your account.");
        }

        $chrome = $this->siteChrome(app(SettingsRepository::class));

        $seo = SeoTagBuilder::build(null, [
            'title' => "Complete Your Payment — {$chrome['siteName']}",
            'description' => 'Complete your Pro Membership payment.',
            'canonical' => route('register.pro'),
            'type' => 'website',
        ], $chrome['general']);

        return view('register.pro-checkout', [
            ...$chrome,
            'seo' => $seo,
            'clientSecret' => $outcome->clientSecret,
            'stripeKey' => (string) config('services.stripe.key'),
        ]);
    }

    public function proComplete(Request $request, User $user): View
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['session_id', 'attempt']), 403);

        $settings = app(SettingsRepository::class);
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Thank You — {$chrome['siteName']}",
            'description' => 'Pro Membership confirmation.',
            'canonical' => url()->current(),
            'type' => 'website',
        ], $chrome['general']);

        $confirmed = $user->hasActiveMembership();
        $attempt = (int) $request->query('attempt', 0);

        return view('register.pro-complete', [
            ...$chrome,
            'seo' => $seo,
            'confirmed' => $confirmed,
            'attempt' => $attempt,
            'message' => $confirmed
                ? $settings->get(
                    'registration',
                    'pro_confirmation_message',
                    'Thank you for your registration and for becoming a Pro Member! Please verify your registered email from your mailbox.',
                )
                : null,
        ]);
    }

    /**
     * Profile fields mirroring the admin User form's "Profile" section
     * (UserForm) field for field — same labels, same max lengths — so a
     * self-registered account can carry the same data an admin-created one
     * can. Only Biography stays optional here; Phone Number, Address and
     * Zip Code are required on the registration form (unlike the admin
     * form, where every one of these is optional). Shared by
     * registerFree/registerPro rather than duplicated.
     *
     * @return array<string, array<int, string>>
     */
    private static function optionalProfileRules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:65535'],
            'address' => ['required', 'string', 'max:500'],
            'zip_code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Site-wide header chrome, same source/shape as HomeController's — kept
     * a private helper here rather than a shared service, matching this
     * codebase's convention (see e.g. CreatesCommerceFixtures's own
     * docblock) of only extracting shared code once duplication across 2+
     * unrelated classes is genuinely the better trade-off; both call sites
     * needing it are methods of this one controller.
     *
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
