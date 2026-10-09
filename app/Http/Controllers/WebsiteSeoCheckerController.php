<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Shared\Support\Features\Features;
use App\Tools\Functionalities\WebsiteSeoChecker;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\Auditor;
use App\Tools\SeoChecker\UrlGuard;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The Website SEO/Metadata Pre-launch Checker's audit endpoint (POST, CSRF,
 * throttle:seo-checker). Answers JSON only; the tool page renders it.
 *
 * Only usable while the tool is live and the Tools feature is on — or by
 * an admin, so the private preview works before publishing. Neither the
 * submitted URL nor the report is stored; failures are logged without them.
 */
class WebsiteSeoCheckerController extends Controller
{
    /** Audits of the same website per minute, across all visitors. */
    public const PER_HOST_PER_MINUTE = 6;

    public function audit(Request $request, Auditor $auditor, UrlGuard $guard, Features $features): JsonResponse
    {
        abort_unless($this->available($features), 404);

        // Honeypot (same field as every public form): bots get a dull refusal.
        if (filled($request->input('hp_website'))) {
            return $this->error('invalid_url', AuditException::MESSAGES['invalid_url'], 422);
        }

        $validator = Validator::make($request->all(), [
            'url' => ['required', 'string', 'max:'.UrlGuard::MAX_LENGTH],
            'intent' => ['nullable', 'in:live,staging'],
        ], [
            'url.required' => 'Enter the address of the page to check.',
            'url.max' => 'That address is too long.',
        ]);

        if ($validator->fails()) {
            return $this->error('invalid_url', $validator->errors()->first(), 422);
        }

        try {
            $url = $guard->normalize((string) $request->input('url'));
        } catch (AuditException $e) {
            return $this->error($e->reason, $e->getMessage(), 422);
        }

        $hostKey = 'seo-checker-host:'.hash('sha256', (string) parse_url($url, PHP_URL_HOST));
        if (RateLimiter::tooManyAttempts($hostKey, self::PER_HOST_PER_MINUTE)) {
            return $this->error('rate_limited', 'This website has been checked several times in the last minute. Please wait a minute and try again.', 429);
        }
        RateLimiter::hit($hostKey, 60);

        if (function_exists('set_time_limit')) {
            @set_time_limit(Auditor::TIME_BUDGET + 30);
        }

        try {
            $report = $auditor->audit($url, $request->input('intent') === 'staging' ? 'staging' : 'live');
        } catch (Throwable $e) {
            Log::error('SEO checker: audit failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return $this->error('server_error', 'Something went wrong on our side while checking this address. Please try again in a moment.', 500);
        }

        return response()->json($report)->withHeaders(['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    private function available(Features $features): bool
    {
        if (Auth::check() && Auth::user()->canAccessPanel(Filament::getPanel('admin'))) {
            return true;
        }

        return $features->enabled('tools')
            && Tool::query()->live()->where('functionality', WebsiteSeoChecker::KEY)->exists();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['status' => 'error', 'error' => ['code' => $code, 'message' => $message]], $status)
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
