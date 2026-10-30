<?php

namespace App\Modules\Newsletter\Http\Middleware;

use App\Modules\Newsletter\Support\NewsletterFeature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404s every Newsletter Marketing admin URL (admin/newsletter*) while
 * NEWSLETTER_MARKETING_ENABLED is off — on top of each resource/page's own
 * canAccess() — so direct URLs reveal nothing. Registered as persistent
 * panel middleware, so it also covers Livewire requests made from a page
 * that was opened before the module was switched off.
 */
class EnsureNewsletterAdminEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! NewsletterFeature::enabled() && $request->is('admin/newsletter', 'admin/newsletter/*', 'admin/newsletter-*')) {
            abort(404);
        }

        return $next($request);
    }
}
