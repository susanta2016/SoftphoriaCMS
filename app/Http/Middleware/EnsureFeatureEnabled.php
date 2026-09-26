<?php

namespace App\Http\Middleware;

use App\Shared\Support\Features\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `feature:<key>[,<key>…]` — answers 404 (not 403: a
 * switched-off feature simply doesn't exist on the public site) unless
 * every listed feature is enabled on Admin → Features Activation.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly Features $features) {}

    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys as $key) {
            abort_unless($this->features->enabled($key), 404);
        }

        return $next($request);
    }
}
