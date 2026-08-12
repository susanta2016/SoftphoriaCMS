<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The 7 Phase-1 page templates (Database Specification §18.3 / Core
 * Specification's Architecture Amendment §A.6 / Jacob Solution
 * Specification's "Content Pages and Templates"). Closed set — CMS pages
 * are independent from the specialized Music/Podcast/Poetry-Prose/
 * Inspirational-Resources/Community modules (Database Specification §18
 * opening line), so this must never grow an entry for one of those; they
 * are never `pages` rows. Backed by the existing pages.template string
 * column — no schema change.
 */
enum PageTemplate: string implements HasLabel
{
    case Standard = 'standard';
    case About = 'about';
    case Faq = 'faq';
    case Legal = 'legal';
    case Contact = 'contact';
    case Archive = 'archive';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard Content Page',
            self::About => 'About Page',
            self::Faq => 'FAQ Page',
            self::Legal => 'Legal/Policy Page',
            self::Contact => 'Contact Page',
            self::Archive => 'Archive/Landing Page',
            self::Custom => 'Custom Structured Page',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $template) => [$template->value => $template->getLabel()])
            ->all();
    }
}
