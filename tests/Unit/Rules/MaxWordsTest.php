<?php

namespace Tests\Unit\Rules;

use App\Rules\MaxWords;
use Tests\TestCase;

/**
 * Direct unit coverage of the word-counting rule itself, independent of any
 * HTTP round-trip — Tests\Feature\PoetryProse\PoetryProseReviewTest exercises
 * the same rule through the real form submission.
 */
class MaxWordsTest extends TestCase
{
    public function test_content_below_the_limit_passes(): void
    {
        $this->assertPasses(str_repeat('word ', 49), 50);
    }

    public function test_content_at_exactly_the_limit_passes(): void
    {
        $this->assertPasses(implode(' ', array_fill(0, 50, 'word')), 50);
    }

    public function test_content_over_the_limit_fails(): void
    {
        $this->assertFails(implode(' ', array_fill(0, 51, 'word')), 50);
    }

    public function test_multiple_whitespace_between_words_does_not_inflate_the_count(): void
    {
        $content = implode("  \t \n  ", array_fill(0, 50, 'word'));

        $this->assertPasses($content, 50);
    }

    public function test_punctuation_attached_to_a_word_counts_as_a_single_word(): void
    {
        $content = "grateful, for it's a piece.";

        $this->assertPasses($content, 5);
        $this->assertFails($content, 4);
    }

    public function test_empty_content_counts_as_zero_words(): void
    {
        $this->assertPasses('', 0);
    }

    private function assertPasses(string $content, int $max): void
    {
        $failed = false;
        (new MaxWords($max))->validate('content', $content, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected content to pass the '.$max.'-word limit.');
    }

    private function assertFails(string $content, int $max): void
    {
        $failed = false;
        (new MaxWords($max))->validate('content', $content, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertTrue($failed, 'Expected content to fail the '.$max.'-word limit.');
    }
}
