<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Models\Order;

/**
 * Session-held guest download access, isolated per Order (public_id) —
 * mirrors App\Modules\Music\Support\CartSession's static/session-array
 * pattern rather than inventing a new convention. Holds the raw guest
 * entitlement token(s) captured from the emailed link just long enough for
 * this browser session to use them; the database only ever stores their
 * SHA-256 hash (Entitlement::access_token_hash), so this is the only place
 * a raw token exists after the email is sent. `verified` is set only once
 * VerifyGuestOrderAccessAction confirms the submitted email matches
 * $order->purchaser_email — possessing the tokens alone is never enough
 * (see GuestDownloadController).
 */
class GuestOrderAccessSession
{
    private const SESSION_KEY = 'guest_order_access';

    /**
     * @param  array<string, string>  $tokens  entitlement public_id => raw token
     */
    public static function storeTokens(Order $order, array $tokens): void
    {
        $state = self::stateFor($order);
        $state['tokens'] = [...$state['tokens'], ...$tokens];
        self::putStateFor($order, $state);
    }

    public static function markVerified(Order $order): void
    {
        $state = self::stateFor($order);
        $state['verified'] = true;
        self::putStateFor($order, $state);
    }

    public static function isVerified(Order $order): bool
    {
        return self::stateFor($order)['verified'];
    }

    public static function hasTokens(Order $order): bool
    {
        return self::stateFor($order)['tokens'] !== [];
    }

    public static function tokenFor(Order $order, string $entitlementPublicId): ?string
    {
        return self::stateFor($order)['tokens'][$entitlementPublicId] ?? null;
    }

    /**
     * @return array{verified: bool, tokens: array<string, string>}
     */
    private static function stateFor(Order $order): array
    {
        return session(self::key($order), ['verified' => false, 'tokens' => []]);
    }

    /**
     * @param  array{verified: bool, tokens: array<string, string>}  $state
     */
    private static function putStateFor(Order $order, array $state): void
    {
        session([self::key($order) => $state]);
    }

    private static function key(Order $order): string
    {
        return self::SESSION_KEY.'.'.$order->public_id;
    }
}
