<?php

namespace App\Modules\Newsletter\Filament\Resources\Templates\Pages;

use App\Modules\Newsletter\Filament\Resources\Templates\NewsletterTemplateResource;
use App\Modules\Newsletter\Filament\Support\NewsletterPreviewActions;
use App\Shared\Services\AuditLogService;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Preview / Send test use the saved version — save first to preview edits.
 */
class EditNewsletterTemplate extends EditRecord
{
    protected static string $resource = NewsletterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NewsletterPreviewActions::preview(),
            NewsletterPreviewActions::sendTest(),
            ActionGroup::make([
                NewsletterTemplateResource::duplicateAction(),
                NewsletterTemplateResource::deleteAction(DeleteAction::make()),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'updated_by' => Auth::id()];
    }

    protected function afterSave(): void
    {
        app(AuditLogService::class)->record(Auth::user(), 'newsletter_template.updated', $this->getRecord());
    }
}
