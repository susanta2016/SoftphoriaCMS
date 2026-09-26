<?php

namespace Tests\Support;

use App\Shared\Support\Spam\FormTimeTrap;

/**
 * For tests that submit a public form protected by FormTimeTrap: returns a
 * token as if the form had been rendered long enough ago for a human to
 * have filled it in.
 */
trait PassesFormTimeTrap
{
    protected function formStartedToken(int $secondsAgo = 30): string
    {
        $this->travel(-$secondsAgo)->seconds();
        $token = app(FormTimeTrap::class)->issue();
        $this->travelBack();

        return $token;
    }
}
