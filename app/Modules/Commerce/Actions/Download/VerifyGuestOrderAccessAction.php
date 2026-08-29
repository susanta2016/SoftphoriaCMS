<?php

namespace App\Modules\Commerce\Actions\Download;

use App\Modules\Commerce\Models\Order;

/**
 * The second, independent gate of guest download access (see
 * GuestDownloadController) — possession of the emailed entitlement token(s)
 * proves nothing on its own, so this must also match before
 * GuestOrderAccessSession is marked verified. Pure and side-effect free:
 * session mutation stays in the controller, this only answers the question.
 * Case-insensitive/trimmed since purchaser_email was typed once at checkout
 * and shouldn't be re-typed byte-for-byte to match.
 */
class VerifyGuestOrderAccessAction
{
    public function verify(Order $order, string $submittedEmail): bool
    {
        return strcasecmp(trim($submittedEmail), trim($order->purchaser_email)) === 0;
    }
}
