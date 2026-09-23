<?php

namespace App\Shared\Support\Pages;

/**
 * WEB-102 browser-verification pass — the closed set of generic decorative
 * icons a Gallery section's item can pick (PageForm's Repeater), rendered
 * by resources/views/components/site/icon.blade.php. Deliberately a small,
 * curated list of hand-drawn inline SVGs (not an icon library dependency,
 * not arbitrary markup) — same "closed list, never a page builder"
 * reasoning as PageSectionType itself. WEB-103 added the line icons the
 * redesigned homepage needs, plus TechLogos' brand logos as a second group.
 */
class GalleryItemIcons
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'design' => 'Design (palette)',
            'code' => 'Code (brackets)',
            'mobile' => 'Mobile app (device)',
            'monitor' => 'Website (monitor)',
            'cart' => 'E-commerce (cart)',
            'cloud' => 'Cloud',
            'nodes' => 'Integrations (connected nodes)',
            'document' => 'CMS / content (document)',
            'users' => 'People (users)',
            'cog' => 'Build (cog)',
            'link' => 'Integrate (chain link)',
            'headset' => 'Support (headset)',
            'search' => 'Discovery (magnifying glass)',
            'plan' => 'Planning (clipboard)',
            'gear' => 'Execution (gear)',
            'rocket' => 'Delivery (rocket)',
        ];
    }

    /**
     * Line icons and technology logos together, grouped for a Select.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return [
            'Icons' => self::options(),
            'Technology logos' => TechLogos::options(),
        ];
    }
}
