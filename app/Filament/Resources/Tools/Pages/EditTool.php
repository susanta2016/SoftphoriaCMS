<?php

namespace App\Filament\Resources\Tools\Pages;

use App\Enums\ToolStatus;
use App\Filament\Resources\Tools\ToolResource;
use App\Models\Tool;
use App\Tools\ToolPublisher;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Save keeps the current status (a draft stays a draft; a published tool's
 * edits go live on save). Preview saves first, then opens the private
 * preview; Publish/Unpublish are explicit actions.
 */
class EditTool extends EditRecord
{
    use ManagesToolFormData;

    protected static string $resource = ToolResource::class;

    private ?string $slugBeforeSave = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label(fn (Tool $record): string => $record->status === ToolStatus::Published ? 'Save changes' : 'Save draft')
                ->formId('form'),
            ToolResource::previewAction(),
            ToolResource::publishAction(),
            ToolResource::unpublishAction(),
            ToolResource::duplicateAction(),
            ToolResource::deleteAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Tool $tool */
        $tool = $this->record;

        $data = $this->fillSeo($data, $tool->seo, 'tools/'.$tool->slug);
        $data['og_image_media_id'] = $tool->seo?->og_image_media_id;
        $data['related_tool_ids'] = $tool->relatedTools()->pluck('tools.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->slugBeforeSave = $this->record->getOriginal('slug');

        $data = $this->pullToolExtras($data);
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Tool $tool */
        $tool = $this->record;
        $this->persistToolExtras($tool);

        if ($this->slugBeforeSave !== null) {
            app(ToolPublisher::class)->recordSlugChange($tool, $this->slugBeforeSave);
        }
    }
}
