<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The Phase-1 structured section/block types a page_sections row may hold
 * (Database Specification §18.2's examples + the Core Specification
 * Architecture Amendment §A.5's reusable-block list + the Jacob Solution
 * Specification's example block list). This is a structured section
 * builder, not a drag-and-drop page builder (Database Specification
 * §18.5) — admins choose from this closed list, never arbitrary HTML.
 *
 * contact_form/newsletter_signup/featured_content are selectable now but
 * functionally inert until ADMIN-009/010 and the JACOB-* content modules
 * exist — the schema is explicitly designed to allow that (§18.5: "allow
 * new section types to be introduced later without changing the pages
 * table"), so placing them now costs nothing.
 */
enum PageSectionType: string implements HasLabel
{
    case Hero = 'hero';
    case RichText = 'rich_text';
    case ImageText = 'image_text';
    case Faq = 'faq';
    case Quote = 'quote';
    case Cta = 'cta';
    case Gallery = 'gallery';
    case NewsletterSignup = 'newsletter_signup';
    case ContactForm = 'contact_form';
    case FeaturedContent = 'featured_content';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::RichText => 'Rich Text',
            self::ImageText => 'Image + Text',
            self::Faq => 'FAQ List',
            self::Quote => 'Quote',
            self::Cta => 'Call to Action',
            self::Gallery => 'Gallery',
            self::NewsletterSignup => 'Newsletter Signup',
            self::ContactForm => 'Contact Form',
            self::FeaturedContent => 'Featured Content',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->getLabel()])
            ->all();
    }
}
