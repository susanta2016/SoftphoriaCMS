<?php

namespace App\Actions\Page\Concerns;

use App\Models\Page;
use App\Models\PageSection;

/**
 * Shared by CreatePageAction/UpdatePageAction/RestorePageRevisionAction —
 * reconciles a Page's page_sections rows against submitted/restored section
 * data: update existing rows (matched by id), create new ones, delete any
 * no longer present. sort_order always follows submission order.
 */
trait SyncsPageSections
{
    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    protected function syncSections(Page $page, array $sections): void
    {
        $keepIds = [];

        foreach (array_values($sections) as $index => $data) {
            $id = $data['id'] ?? null;

            $section = $id ? $page->sections()->whereKey($id)->first() : null;
            $section ??= new PageSection;

            $section->page_id = $page->id;
            $section->section_type = $data['section_type'];
            $section->title = $data['title'] ?? null;
            $section->sort_order = $index;
            $section->is_enabled = $data['is_enabled'] ?? true;
            $section->settings_json = $data['settings_json'] ?? null;
            $section->content_json = $data['content_json'] ?? null;
            $section->save();

            $keepIds[] = $section->id;
        }

        $page->sections()->whereNotIn('id', $keepIds)->delete();
    }
}
