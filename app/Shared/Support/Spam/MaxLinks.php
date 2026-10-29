<?php

namespace App\Shared\Support\Spam;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects free text carrying more than $max links (http://, https:// or
 * www.) — link-stuffed messages are the typical SEO-spam payload, and a
 * real message rarely needs more than a couple. Shared by the public
 * Contact Us and Inspirational Resources submission forms.
 */
class MaxLinks implements ValidationRule
{
    public function __construct(private readonly int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match_all('~https?://|www\.~i', $value) > $this->max) {
            $fail("Please include no more than {$this->max} links in your message.");
        }
    }
}
