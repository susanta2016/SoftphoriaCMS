<?php

namespace App\Http\Middleware;

use App\Shared\Support\Marketing\UtmAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records first-touch UTM attribution (UtmAttribution) for guests landing
 * on a public page with utm_* parameters. Signed-in users are skipped —
 * attribution is acquisition-at-registration only, and an existing
 * account's is never changed. Appended to the `web` group after
 * StartSession, ahead of BetaAccessGate so a link that lands on the beta
 * password page still counts.
 */
class CaptureUtmAttribution
{
    public function __construct(private readonly UtmAttribution $attribution) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && $request->hasSession()
            && $request->user() === null
            && ! $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*')) {
            $this->attribution->captureFrom($request);
        }

        return $next($request);
    }
}
