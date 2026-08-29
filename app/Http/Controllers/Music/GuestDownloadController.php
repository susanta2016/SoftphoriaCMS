<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction;
use App\Modules\Commerce\Actions\Download\VerifyGuestOrderAccessAction;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\GuestOrderAccessSession;
use App\Modules\Music\Models\Track;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The guest half of Phase 4 — a paid guest Order's download access, reached
 * only via the one-per-order link SendGuestDownloadAccessEmailAction sends.
 * Deliberately separate from TrackDownloadController (registered-user-only,
 * session-authenticated) rather than branching one controller two ways: the
 * two access models don't share a request shape (this one carries an Order,
 * a session-held raw token, and an email-verification step; that one only
 * ever needs Auth::user()).
 *
 * Two independent gates, neither a substitute for the other:
 *  - possession of the emailed entitlement token(s) (captured into
 *    GuestOrderAccessSession, never persisted to the database)
 *  - knowledge of the purchase email (VerifyGuestOrderAccessAction)
 * Every actual download still goes through the existing, unmodified
 * AuthorizeTrackDownloadAction::authorizeForGuest() /
 * ResolveTrackAccessAction::forGuestToken() — this controller never decides
 * download eligibility itself, only whether the two gates above are open.
 */
class GuestDownloadController extends Controller
{
    /**
     * Captures ?t[]=entitlementPublicId.token pairs from the emailed link
     * into the session, then redirects to the clean URL so the raw token(s)
     * don't linger in browser history/referrer headers. Reveals nothing
     * about the order's contents — only ever an email-entry form (or, once
     * already verified this session, a redirect straight to the items page).
     */
    public function show(Request $request, Order $order, SettingsRepository $settings): View|RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Paid && $order->isGuest(), 404);

        $rawTokens = $request->query('t', []);

        if (is_array($rawTokens) && $rawTokens !== []) {
            GuestOrderAccessSession::storeTokens($order, $this->parseTokenPairs($order, $rawTokens));

            return redirect()->route('downloads.guest.show', $order);
        }

        if (GuestOrderAccessSession::isVerified($order)) {
            return redirect()->route('downloads.guest.items', $order);
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Verify Your Purchase — {$chrome['siteName']}",
            'canonical' => route('downloads.guest.show', $order),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('downloads.guest.verify', [
            ...$chrome,
            'seo' => $seo,
            'order' => $order,
        ]);
    }

    public function verify(Request $request, Order $order, VerifyGuestOrderAccessAction $verifyAccess): RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Paid && $order->isGuest(), 404);

        $data = $request->validate(['email' => ['required', 'email']]);

        if (! GuestOrderAccessSession::hasTokens($order) || ! $verifyAccess->verify($order, $data['email'])) {
            return redirect()->route('downloads.guest.show', $order)
                ->withInput()
                ->with('guest_verify_error', 'We couldn\'t verify that email against this order. Please check the email you used when purchasing and try again.');
        }

        GuestOrderAccessSession::markVerified($order);

        return redirect()->route('downloads.guest.items', $order);
    }

    /**
     * Only reachable once GuestOrderAccessSession says this browser session
     * is verified for this exact order — an unverified visitor (even one
     * who reached the URL directly) is bounced back to the email form
     * without seeing a single item title.
     */
    public function items(Order $order, SettingsRepository $settings): View|RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Paid && $order->isGuest(), 404);

        if (! GuestOrderAccessSession::isVerified($order)) {
            return redirect()->route('downloads.guest.show', $order);
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Your Downloads — {$chrome['siteName']}",
            'canonical' => route('downloads.guest.items', $order),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('downloads.guest.items', [
            ...$chrome,
            'seo' => $seo,
            'order' => $order->load('items.album.tracks', 'items.single.track', 'items.entitlement'),
        ]);
    }

    public function download(
        Order $order,
        Track $track,
        AuthorizeTrackDownloadAction $authorize,
        Request $request,
    ): BinaryFileResponse|RedirectResponse {
        abort_unless($order->status === OrderStatus::Paid && $order->isGuest(), 404);

        if (! GuestOrderAccessSession::isVerified($order)) {
            return redirect()->route('downloads.guest.show', $order);
        }

        $entitlement = $order->load('items.entitlement')->items
            ->map(fn ($item) => $item->entitlement)
            ->filter()
            ->first(fn ($entitlement) => $entitlement->coversTrack($track));

        $token = $entitlement !== null ? GuestOrderAccessSession::tokenFor($order, $entitlement->public_id) : null;

        if ($entitlement === null || $token === null) {
            return redirect()->route('downloads.guest.items', $order)
                ->with('download_error', 'You don\'t have download access to this track.');
        }

        $result = $authorize->authorizeForGuest($track, $entitlement->public_id, $token, $request->ip(), $request->userAgent());

        if (! $result->authorized) {
            return redirect()->route('downloads.guest.items', $order)->with('download_error', match ($result->denialReason) {
                'limit_reached' => 'You\'ve reached the download limit for this track.',
                'no_audio_asset' => 'This track has no downloadable file yet.',
                default => 'You don\'t have download access to this track.',
            });
        }

        $media = $result->media;

        return response()->download(
            Storage::disk($media->disk)->path($media->path),
            $media->original_filename,
        );
    }

    /**
     * Only keeps a token pair whose entitlement public_id genuinely belongs
     * to one of this order's own OrderItems — a malformed or foreign
     * entitlement id in the query string is simply dropped here rather than
     * stored, though AuthorizeTrackDownloadAction's own hash check remains
     * the actual authority regardless.
     *
     * @param  array<int, string>  $rawTokens
     * @return array<string, string>
     */
    private function parseTokenPairs(Order $order, array $rawTokens): array
    {
        $validEntitlementIds = $order->items->map(fn ($item) => $item->entitlement?->public_id)->filter()->all();

        $pairs = [];

        foreach ($rawTokens as $rawToken) {
            if (! is_string($rawToken) || ! str_contains($rawToken, '.')) {
                continue;
            }

            [$entitlementPublicId, $token] = explode('.', $rawToken, 2);

            if (in_array($entitlementPublicId, $validEntitlementIds, true)) {
                $pairs[$entitlementPublicId] = $token;
            }
        }

        return $pairs;
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
