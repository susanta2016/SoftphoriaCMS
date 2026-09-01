<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckAlbumReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckTrackReadinessAction;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Commerce\Support\PurchaseReadinessResult;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Support\CartSession;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The Music cart — session-held, never a DB row until checkout (see
 * CartSession's docblock). Digital goods only: no quantity, adding the same
 * release twice is a no-op rather than incrementing anything. 'track' is an
 * individually-purchased Track (almost always Album-owned — a Single-owned
 * track is bought via the 'single' type instead, which already grants
 * exactly that one track) — always priced via GlobalPricingResolver::
 * perSongPrice(), never its parent Album's price.
 */
class CartController extends Controller
{
    public function add(
        Request $request,
        CheckAlbumReadinessAction $checkAlbum,
        CheckSingleReadinessAction $checkSingle,
        CheckTrackReadinessAction $checkTrack,
    ): RedirectResponse {
        $data = $request->validate([
            'type' => ['required', 'in:album,single,track'],
            'slug' => ['required', 'string'],
        ]);

        $item = match ($data['type']) {
            'album' => Album::query()->published()->where('slug', $data['slug'])->first(),
            'single' => Single::query()->published()->where('slug', $data['slug'])->first(),
            'track' => Track::query()->published()->where('slug', $data['slug'])->first(),
        };

        if ($item === null) {
            return back()->with('cart_error', 'That item is no longer available.');
        }

        $user = Auth::user();

        if ($user?->hasActiveMembership()) {
            return back()->with('cart_notice', "\"{$item->title}\" is already included with your Pro Membership — no purchase needed.");
        }

        $alreadyOwned = $item instanceof Track ? $user?->ownsTrack($item) : $user?->ownsRelease($item);

        if ($alreadyOwned) {
            return back()->with('cart_notice', "You already own \"{$item->title}\".");
        }

        $readiness = $this->checkReadiness($item, $checkAlbum, $checkSingle, $checkTrack);

        if (! $readiness->ready) {
            return back()->with('cart_error', "\"{$item->title}\" isn't available for purchase yet.");
        }

        CartSession::add($data['type'], $item->getKey());

        return back()->with('cart_added', "\"{$item->title}\" added to your cart.");
    }

    private function checkReadiness(
        Album|Single|Track $item,
        CheckAlbumReadinessAction $checkAlbum,
        CheckSingleReadinessAction $checkSingle,
        CheckTrackReadinessAction $checkTrack,
    ): PurchaseReadinessResult {
        return match (true) {
            $item instanceof Album => $checkAlbum->handle($item),
            $item instanceof Single => $checkSingle->handle($item),
            default => $checkTrack->handle($item),
        };
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:album,single,track'],
            'id' => ['required', 'integer'],
        ]);

        CartSession::remove($data['type'], (int) $data['id']);

        return redirect()->route('cart.show');
    }

    public function show(SettingsRepository $settings, GlobalPricingResolver $pricing): View
    {
        $chrome = $this->siteChrome($settings);

        $lines = $this->hydratedCartLines();
        $subtotal = $lines->sum('price');

        $seo = SeoTagBuilder::build(null, [
            'title' => "Cart — {$chrome['siteName']}",
            'canonical' => route('cart.show'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('cart.show', [
            ...$chrome,
            'seo' => $seo,
            'lines' => $lines,
            'subtotal' => $subtotal,
        ]);
    }

    /**
     * @return Collection<int, array{type: string, model: Album|Single|Track, title: string, coverUrl: ?string, price: float, showRoute: string}>
     */
    public function hydratedCartLines(): Collection
    {
        $pricing = app(GlobalPricingResolver::class);
        $lines = collect();

        foreach (CartSession::items() as $entry) {
            $model = match ($entry['type']) {
                'album' => Album::query()->with('cover')->find($entry['id']),
                'single' => Single::query()->with('cover')->find($entry['id']),
                'track' => Track::query()->with(['album.cover', 'single.cover'])->find($entry['id']),
                default => null,
            };

            $isPublished = $model instanceof Track
                ? $model->status === TrackStatus::Published
                : $model?->status === ReleaseStatus::Published;

            if ($model === null || ! $isPublished) {
                continue;
            }

            // A Track has no cover of its own — it displays whichever
            // parent (Album or Single) actually owns it, purely cosmetic;
            // the price/entitlement below never depend on that parent.
            $cover = $model instanceof Track ? ($model->album?->cover ?? $model->single?->cover) : $model->cover;

            $lines->push([
                'type' => $entry['type'],
                'model' => $model,
                'title' => $model->title,
                'coverUrl' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
                'price' => (float) ($entry['type'] === 'album' ? $pricing->fullAlbumPrice() : $pricing->perSongPrice()),
                'showRoute' => match (true) {
                    $entry['type'] === 'album' => route('music.albums.show', $model),
                    $entry['type'] === 'single' => route('music.singles.show', $model),
                    default => $model->single_id !== null ? route('music.singles.show', $model->single) : route('music.tracks.show', $model),
                },
            ]);
        }

        return $lines;
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
