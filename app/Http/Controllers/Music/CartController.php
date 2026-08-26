<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckAlbumReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
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
 * release twice is a no-op rather than incrementing anything.
 */
class CartController extends Controller
{
    public function add(Request $request, CheckAlbumReadinessAction $checkAlbum, CheckSingleReadinessAction $checkSingle): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:album,single'],
            'slug' => ['required', 'string'],
        ]);

        $item = $data['type'] === 'album'
            ? Album::query()->published()->where('slug', $data['slug'])->first()
            : Single::query()->published()->where('slug', $data['slug'])->first();

        if ($item === null) {
            return back()->with('cart_error', 'That item is no longer available.');
        }

        $user = Auth::user();

        if ($user?->hasActiveMembership()) {
            return back()->with('cart_notice', "\"{$item->title}\" is already included with your Pro Membership — no purchase needed.");
        }

        if ($user?->ownsRelease($item)) {
            return back()->with('cart_notice', "You already own \"{$item->title}\".");
        }

        $readiness = $data['type'] === 'album' ? $checkAlbum->handle($item) : $checkSingle->handle($item);

        if (! $readiness->ready) {
            return back()->with('cart_error', "\"{$item->title}\" isn't available for purchase yet.");
        }

        CartSession::add($data['type'], $item->getKey());

        return back()->with('cart_notice', "\"{$item->title}\" added to your cart.");
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:album,single'],
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
     * @return Collection<int, array{type: string, model: Album|Single, title: string, coverUrl: ?string, price: float, showRoute: string}>
     */
    public function hydratedCartLines(): Collection
    {
        $pricing = app(GlobalPricingResolver::class);
        $lines = collect();

        foreach (CartSession::items() as $entry) {
            $model = $entry['type'] === 'album'
                ? Album::query()->with('cover')->find($entry['id'])
                : Single::query()->with('cover')->find($entry['id']);

            if ($model === null || $model->status !== ReleaseStatus::Published) {
                continue;
            }

            $cover = $model->cover;

            $lines->push([
                'type' => $entry['type'],
                'model' => $model,
                'title' => $model->title,
                'coverUrl' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
                'price' => (float) ($entry['type'] === 'album' ? $pricing->fullAlbumPrice() : $pricing->perSongPrice()),
                'showRoute' => $entry['type'] === 'album'
                    ? route('music.albums.show', $model)
                    : route('music.singles.show', $model),
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
