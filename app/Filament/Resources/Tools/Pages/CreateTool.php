<?php

namespace App\Filament\Resources\Tools\Pages;

use App\Filament\Resources\Tools\ToolResource;
use App\Models\Tool;
use App\Shared\Services\AuditLogService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

/**
 * New tools always start as a Draft; publishing is a separate, validated
 * step (ToolPublisher).
 */
class CreateTool extends CreateRecord
{
    use ManagesToolFormData;

    protected static string $resource = ToolResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Add Tool';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Save draft');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->pullToolExtras($data);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Tool $tool */
        $tool = $this->record;
        $this->persistToolExtras($tool);

        app(AuditLogService::class)->record(Auth::user(), 'tool.created', $tool, ['slug' => $tool->slug]);
    }

    protected function getRedirectUrl(): string
    {
        return ToolResource::getUrl('edit', ['record' => $this->record]);
    }
}
