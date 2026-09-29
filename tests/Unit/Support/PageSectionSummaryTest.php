<?php

namespace Tests\Unit\Support;

use App\Enums\PageSectionType;
use App\Shared\Support\Pages\PageSectionSummary;
use PHPUnit\Framework\TestCase;

class PageSectionSummaryTest extends TestCase
{
    public function test_label_numbers_the_section_and_adds_a_distinct_admin_title(): void
    {
        $this->assertSame('01 · Hero', PageSectionSummary::label(['section_type' => 'hero'], 0));
        $this->assertSame('04 · Gallery — Why Softphoria', PageSectionSummary::label(['section_type' => 'gallery', 'title' => 'Why Softphoria'], 3));
        $this->assertSame('Rich Text', PageSectionSummary::label(['section_type' => 'rich_text', 'title' => 'Rich Text']));
    }

    public function test_label_marks_hidden_sections(): void
    {
        $this->assertSame('02 · Quote (hidden)', PageSectionSummary::label(['section_type' => 'quote', 'is_enabled' => false], 1));
    }

    public function test_hero_summary_joins_heading_highlight_and_stats(): void
    {
        $summary = PageSectionSummary::summary(['section_type' => 'hero', 'content_json' => [
            'heading' => 'Technology that moves your',
            'heading_highlight' => 'business forward.',
            'stats' => [['value' => '20+', 'label' => 'Years'], ['value' => '15–20', 'label' => 'Projects']],
        ]]);

        $this->assertSame('Technology that moves your business forward. — 20+ Years · 15–20 Projects', $summary);
    }

    public function test_rich_text_summary_is_plain_text_with_paragraph_breaks_as_spaces(): void
    {
        $summary = PageSectionSummary::summary(['section_type' => 'rich_text', 'content_json' => [
            'body' => '<h2>Great websites</h2><p>are digital tools&nbsp;for&nbsp;growth.</p>',
        ]]);

        $this->assertSame('Great websites are digital tools for growth.', $summary);
    }

    public function test_long_text_is_truncated(): void
    {
        $summary = PageSectionSummary::summary(['section_type' => 'quote', 'content_json' => ['quote' => str_repeat('word ', 60)]]);

        $this->assertLessThanOrEqual(PageSectionSummary::MAX_LENGTH + 3, mb_strlen($summary));
        $this->assertStringEndsWith('...', $summary);
    }

    public function test_faq_and_gallery_summaries_count_their_items(): void
    {
        $this->assertSame('6 questions', PageSectionSummary::summary(['section_type' => 'faq', 'content_json' => [
            'items' => array_fill(0, 6, ['question' => 'Q?', 'answer' => 'A.']),
        ]]));
        $this->assertSame('Our work · 2 images', PageSectionSummary::summary(['section_type' => 'gallery', 'content_json' => [
            'heading' => 'Our work',
            'gallery_items' => [['media_id' => 1], ['media_id' => 2]],
        ]]));
        $this->assertSame('Trusted Technologies · 2 items', PageSectionSummary::summary(['section_type' => 'gallery', 'content_json' => [
            'eyebrow' => 'Trusted Technologies',
            'gallery_items' => [['title' => 'Laravel', 'icon' => 'laravel'], ['media_id' => 5]],
        ]]));
    }

    public function test_a_legacy_media_ids_gallery_is_counted(): void
    {
        $this->assertSame('3 images', PageSectionSummary::summary(['section_type' => 'gallery', 'content_json' => ['media_ids' => [1, 2, 3]]]));
    }

    public function test_quote_summary_includes_the_attribution(): void
    {
        $this->assertSame('Less is more. — Mies', PageSectionSummary::summary(['section_type' => 'quote', 'content_json' => [
            'quote' => 'Less is more.', 'attribution' => 'Mies',
        ]]));
    }

    public function test_module_driven_sections_describe_what_they_show(): void
    {
        $this->assertSame('Selected projects · Up to 4 featured projects', PageSectionSummary::summary(['section_type' => 'portfolio', 'content_json' => ['heading' => 'Selected projects', 'limit' => '4']]));
        $this->assertSame('Latest 3 posts', PageSectionSummary::summary(['section_type' => 'blog_posts', 'content_json' => []]));
        $this->assertSame('2 selected services', PageSectionSummary::summary(['section_type' => 'services', 'content_json' => ['service_slugs' => ['a', 'b']]]));
        $this->assertSame('Homepage services', PageSectionSummary::summary(['section_type' => 'services', 'content_json' => []]));
    }

    public function test_every_section_type_has_a_non_empty_summary_even_with_no_content(): void
    {
        foreach (PageSectionType::cases() as $type) {
            $this->assertNotSame('', PageSectionSummary::summary(['section_type' => $type->value, 'content_json' => null]), $type->value);
        }
    }

    public function test_empty_sections_fall_back_to_the_section_type(): void
    {
        $this->assertSame('Hero section', PageSectionSummary::summary(['section_type' => 'hero', 'content_json' => []]));
        $this->assertSame('FAQ List section', PageSectionSummary::summary(['section_type' => 'faq', 'content_json' => ['items' => []]]));
        $this->assertSame('Untyped section', PageSectionSummary::summary([]));
    }
}
