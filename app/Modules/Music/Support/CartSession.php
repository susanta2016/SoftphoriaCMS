<?php

namespace App\Modules\Music\Support;

/**
 * The Music cart is session-held {type, id} pairs, never a DB row, until
 * checkout actually creates the real Order — see
 * App\Http\Controllers\Music\CheckoutController's docblock for why (Order.
 * purchaser_email is required NOT NULL, but a guest must be able to add
 * items to their cart without providing anything). Keying by "type:id"
 * gives duplicate-add prevention for free — adding the same release twice
 * just overwrites the same array key, matching the "no quantity, no
 * duplicate line items" rule for digital goods.
 */
class CartSession
{
    private const SESSION_KEY = 'music_cart';

    /**
     * @return array<string, array{type: string, id: int}>
     */
    public static function items(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public static function count(): int
    {
        return count(self::items());
    }

    public static function add(string $type, int $id): void
    {
        $items = self::items();
        $items["{$type}:{$id}"] = ['type' => $type, 'id' => $id];
        session([self::SESSION_KEY => $items]);
    }

    public static function remove(string $type, int $id): void
    {
        $items = self::items();
        unset($items["{$type}:{$id}"]);
        session([self::SESSION_KEY => $items]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function has(string $type, int $id): bool
    {
        return array_key_exists("{$type}:{$id}", self::items());
    }
}
