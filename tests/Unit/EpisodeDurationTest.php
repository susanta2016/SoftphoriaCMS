<?php

namespace Tests\Unit;

use App\Modules\Podcast\Support\EpisodeDuration;
use PHPUnit\Framework\TestCase;

class EpisodeDurationTest extends TestCase
{
    public function test_no_duration_has_no_label(): void
    {
        $this->assertNull(EpisodeDuration::label(null));
        $this->assertNull(EpisodeDuration::label(0));
        $this->assertNull(EpisodeDuration::label(-5));
    }

    public function test_under_a_minute_reads_less_than_1_min(): void
    {
        $this->assertSame('Less than 1 min', EpisodeDuration::label(1));
        $this->assertSame('Less than 1 min', EpisodeDuration::label(59));
    }

    public function test_whole_minutes_are_truncated_as_before(): void
    {
        $this->assertSame('1 min', EpisodeDuration::label(60));
        $this->assertSame('1 min', EpisodeDuration::label(119));
        $this->assertSame('25 min', EpisodeDuration::label(1500));
    }
}
