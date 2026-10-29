<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ForceLoginAsUserAction;
use App\Exceptions\Users\ForceLoginNotAllowedException;
use App\Filament\Resources\Users\UserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST endpoint behind Admin > Users "Force Login" (see
 * ForceLoginAsUserAction for the session switch itself). Every check is
 * repeated server-side on each request — hiding the action in the UI is
 * never relied on:
 *  - feature flag off → 404, as if the endpoint didn't exist;
 *  - caller must be an admin who may use the panel (same
 *    canAccessPanel() gate as /admin, like StreamMediaController) → 403;
 *  - target eligibility → back to the Users list with the reason.
 * CSRF is enforced by the `web` middleware group; `auth` and a throttle
 * are applied on the route.
 */
class ForceLoginController extends Controller
{
    public function __invoke(Request $request, User $user, ForceLoginAsUserAction $forceLogin): RedirectResponse
    {
        abort_unless(config('features.force_login_enabled'), 404);

        /** @var ?User $actor */
        $actor = $request->user();

        abort_unless($actor !== null && $actor->canAccessPanel(Filament::getPanel('admin')), 403);

        try {
            $forceLogin->handle($user, $actor, $request);
        } catch (ForceLoginNotAllowedException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return redirect()->to(UserResource::getUrl('index'));
        }

        return redirect()->route('account.dashboard')
            ->with('status', "You are now signed in as {$user->displayName()}.");
    }
}
