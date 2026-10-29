<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Actions\Download\AuthorizeAlbumZipDownloadAction;
use App\Modules\Commerce\Models\Entitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The signed-in purchaser's full-album ZIP download, linked from
 * /account/orders. The entitlement is bound by its public_id, but ownership
 * is never inferred from the URL: AuthorizeAlbumZipDownloadAction only
 * grants it when entitlement.user_id is Auth::user() — anyone else's
 * entitlement id is denied (and logged) exactly like a missing purchase.
 */
class AlbumZipDownloadController extends Controller
{
    public function __invoke(Request $request, Entitlement $entitlement, AuthorizeAlbumZipDownloadAction $authorize): BinaryFileResponse|RedirectResponse
    {
        $result = $authorize->authorizeForUser($entitlement, $request->user(), $request->ip(), $request->userAgent());

        if (! $result->authorized) {
            return redirect()->route('account.orders')->with('download_error', match ($result->denialReason) {
                'limit_reached' => 'You\'ve reached the download limit for this album ZIP.',
                'no_audio_asset' => 'This album has no downloadable files yet.',
                default => 'You don\'t have download access to this album.',
            });
        }

        return response()->download($result->zipPath, $result->filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
