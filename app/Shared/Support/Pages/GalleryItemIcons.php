<?php

namespace App\Shared\Support\Pages;

/**
 * WEB-102 browser-verification pass — the closed set of generic decorative
 * icons a Gallery section's item can pick (PageForm's Repeater), rendered
 * by resources/views/components/site/icon.blade.php. Deliberately a small,
 * curated list of hand-drawn inline SVGs (not an icon library dependency,
 * not arbitrary markup) — same "closed list, never a page builder"
 * reasoning as PageSectionType itself.
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
            'search' => 'Discovery (magnifying glass)',
            'plan' => 'Planning (clipboard)',
            'gear' => 'Execution (gear)',
            'rocket' => 'Delivery (rocket)',
        ];
    }
}
