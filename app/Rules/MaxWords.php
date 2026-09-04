<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a string with more than N whitespace-separated words — used by
 * PoetryProseReviewController for the Light Posts 50-word comment limit
 * (config('features.poetry_prose_comment_max_words')), which is deliberately
 * word-counted rather than character-counted like every other module's
 * config('reviews.max_length'). "Word" is any run of non-whitespace
 * characters; punctuation attached to a word (e.g. "grateful," or "it's")
 * counts as one word, matching how a member would naturally count words
 * themselves. Never truncates — a submission over the limit fails validation
 * and is rejected outright, the same as every other rule in this app.
 */
class MaxWords implements ValidationRule
{
    public function __construct(private readonly int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $words = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $count = count($words);

        if ($count > $this->max) {
            $fail("The :attribute must not be greater than {$this->max} words.");
        }
    }
}
