<?php

namespace App\Shared\Support\Spam;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * A "time trap" for public forms: the rendered form carries an encrypted,
 * tamper-proof timestamp of when it was served, and a submission that comes
 * back faster than a human could possibly fill it in (or without a valid
 * token at all) is treated as a bot. Complements the hp_website honeypot
 * and route throttling — scripted spam typically POSTs within milliseconds
 * of fetching the page, or never fetches the page at all.
 *
 * The token is encrypted with APP_KEY, so a bot can't forge an older
 * timestamp; it only proves *when* the page was served, not who served it.
 */
class FormTimeTrap
{
    /** Hidden input / data attribute name carrying the token. */
    public const FIELD = '_started';

    /** Minimum seconds between page render and a form submission. */
    public const SUBMIT_MIN_SECONDS = 3;

    /** Minimum seconds between page render and revealing contact details. */
    public const REVEAL_MIN_SECONDS = 1;

    public function issue(): string
    {
        // Milliseconds — whole seconds would let a request that lands just
        // after a second boundary count as a full second old.
        return Crypt::encryptString((string) now()->getTimestampMs());
    }

    public function passes(mixed $token, int $minSeconds): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $issuedAt = Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        if (! ctype_digit($issuedAt)) {
            return false;
        }

        return now()->getTimestampMs() - (int) $issuedAt >= $minSeconds * 1000;
    }
}
