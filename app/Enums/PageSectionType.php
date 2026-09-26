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
 * newsletter_signup/featured_content are selectable now but functionally
 * inert until ADMIN-009 and the JACOB-* content modules exist — the schema
 * is explicitly designed to allow that (§18.5: "allow new section types to
 * be introduced later without changing the pages table"), so placing them
 * now costs nothing. contact_form was wired up in WEB-101 and renders the
 * real, shared contact form (resources/views/components/site/contact-form.blade.php).
 * testimonials (WEB-103) renders every enabled row from the admin
 * Testimonials resource as a slider — the section itself only carries its
 * heading and background image. portfolio renders the published + featured
 * rows from the admin Portfolio resource as project cards (up to
 * content_json.limit) — the section carries only its heading/links.
 * blog_posts shows the newest live blog posts the same way (hidden while
 * the Blog Posts feature is off or nothing is published). services lists the
 * published services marked "Show on homepage" from Admin → Services.
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
    case Testimonials = 'testimonials';
    case Portfolio = 'portfolio';
    case BlogPosts = 'blog_posts';
    case Services = 'services';

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
            self::Testimonials => 'Testimonials',
            self::Portfolio => 'Featured Portfolio',
            self::BlogPosts => 'Latest Blog Posts',
            self::Services => 'Services',
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
