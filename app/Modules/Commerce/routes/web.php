<?php

use App\Modules\Commerce\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * The one route this task adds — a technical, backend-only Stripe webhook
 * endpoint (§18 of the approved brief). Not a "public Music URL" (§20:
 * don't finalize those) and not a customer-facing page (§11: no frontend
 * checkout/download routes in this task) — it exists to receive events from
 * Stripe's servers only, authenticated by webhook signature rather than
 * CSRF (see bootstrap/app.php's validateCsrfTokens exclusion).
 */
Route::post('/commerce/webhooks/stripe', StripeWebhookController::class)
    ->middleware('web')
    ->name('commerce.webhooks.stripe');
