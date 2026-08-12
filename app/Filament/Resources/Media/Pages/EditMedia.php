<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

/**
 * View + Edit combined on one page (docs/Reference UI/Admin/Media
 * Edit-details.docx) — there is no separate ViewMedia page/route anymore,
 * so no ViewAction here; MediaForm carries the read-only details alongside
 * the editable fields and the Replace File action.
 *
 * ->formId('form') on the Save button — see EditUser's docblock (the header
 * Save button pattern requires it, or the button silently does nothing).
 */
class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return "View {$this->getRecord()->original_filename}";
    }

    /**
     * Polled by the Responsive Variants section (MediaForm) while a
     * generation job is in flight — GenerateImageVariantsJob runs on a
     * queue worker, a separate process from this request, so nothing else
     * tells this page the job finished. Without polling, the section is
     * frozen on whatever partial state happened to be true at the moment
     * the regenerate/replace action's own response rendered (which, on a
     * fast queue, can even race the job itself), until the admin manually
     * reloads the page. refresh() reloads both the record's own columns
     * and its already-loaded `variants` relation, since content
     * closures reuse this same cached record instance.
     */
    public function refreshVariantsStatus(): void
    {
        $this->record->refresh();
    }
}
