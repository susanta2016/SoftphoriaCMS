<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Content only (no schema change): seeded SEO titles and descriptions that
 * were longer than the admin form allows (SeoFields: Meta Title 60, Meta
 * Description 160 characters). They displayed fine, but blocked any admin
 * save of their record until shortened. The seeders now use the same short
 * values, and tests/Feature/SeededSeoLimitsTest stops a long one being
 * seeded again.
 *
 * Only a value that still matches the old seeded text exactly is changed;
 * one an admin has already edited is left alone. Running it again, or on a
 * database without these records, changes nothing.
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    /** old seeded title => new title */
    private const TITLES = [
        'About Softphoria — 20+ Years Building Powerful Web Experiences' => 'About Softphoria — 20+ Years of Powerful Web Experiences',
        'YouTube Thumbnail Size & Image Requirements 2026 | Softphoria' => 'YouTube Thumbnail Size & Requirements 2026 | Softphoria',
        'Compress Image to 20KB Online — Free Image Size Reducer | Softphoria' => 'Compress Image to 20KB — Free Size Reducer | Softphoria',
        'Compress Image to 30KB Online — Free, Keeps Quality | Softphoria' => 'Compress Image to 30KB — Free, Keeps Quality | Softphoria',
        'Compress Image to 50KB Online — Free Photo Compressor | Softphoria' => 'Compress Image to 50KB — Free Photo Compressor | Softphoria',
        'Compress Image to 100KB Online — Free, No Upload | Softphoria' => 'Compress Image to 100KB — Free, No Upload | Softphoria',
        'Compress Image to 200KB Online — Free Image Optimizer | Softphoria' => 'Compress Image to 200KB — Free Image Optimizer | Softphoria',
        'Compress Image to 500KB Online — Free, Keeps Detail | Softphoria' => 'Compress Image to 500KB — Free, Keeps Detail | Softphoria',
        'Compress Image to 1MB Online — Free, Full Size Kept | Softphoria' => 'Compress Image to 1MB — Free, Full Size Kept | Softphoria',
        'Instagram Reels Safe Zone (1080×1920): Measured Margins | Softphoria' => 'Instagram Reels Safe Zone (1080×1920): Measured Margins',
        'YouTube Shorts Safe Zone (1080×1920): Measured Margins | Softphoria' => 'YouTube Shorts Safe Zone (1080×1920): Measured Margins',
    ];

    /** old seeded description => new description */
    private const DESCRIPTIONS = [
        'We design, build and support high-performance websites, custom software, cloud infrastructure and integrations — helping businesses turn ideas into real world solutions.' => 'We design, build and support high-performance websites, custom software, cloud and integrations, turning business ideas into real-world solutions.',
        'Every key image size for Instagram, Facebook, LinkedIn, X, YouTube and Pinterest in one table, with which values are platform limits and which are recommendations.' => 'Every key image size for Instagram, Facebook, LinkedIn, X, YouTube and Pinterest in one table, showing which values are limits and which are recommendations.',
        'Observed on real phones in October 2026: keep the top 190 px, right 160 px and bottom 220 px of a 1080×1920 Reel clear. See the caption-open and auto-caption areas too.' => 'Observed on real phones in October 2026: keep the top 190 px, right 160 px and bottom 220 px of a 1080×1920 Reel clear, plus the caption and auto-caption areas.',
        'Observed on real phones in October 2026: keep the top 180 px, right 170 px and bottom 190 px of a 1080×1920 Short clear, plus the auto-caption band near the top.' => 'Observed on real phones in October 2026: keep the top 180 px, right 170 px and bottom 190 px of a 1080×1920 Short clear, plus the auto-caption band at the top.',
        'See where Instagram Reels and YouTube Shorts buttons, captions and top bars cover your vertical video, with suggested fixes. Runs in your browser; nothing is uploaded.' => 'See where Instagram Reels and YouTube Shorts buttons, captions and top bars cover your vertical video, with fixes. Runs in your browser; nothing is uploaded.',
    ];

    public function up(): void
    {
        $this->replace('meta_title', self::TITLES);
        $this->replace('meta_description', self::DESCRIPTIONS);
    }

    public function down(): void
    {
        $this->replace('meta_title', array_flip(self::TITLES));
        $this->replace('meta_description', array_flip(self::DESCRIPTIONS));
    }

    /** @param array<string, string> $values */
    private function replace(string $column, array $values): void
    {
        foreach ($values as $old => $new) {
            DB::table('seo_metadata')->where($column, $old)->update([$column => $new, 'updated_at' => now()]);
        }
    }
};
