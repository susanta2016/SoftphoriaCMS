<?php

namespace App\Modules\Newsletter\Filament\Resources\Templates\Pages;

use App\Modules\Newsletter\Filament\Resources\Templates\NewsletterTemplateResource;
use App\Shared\Services\AuditLogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateNewsletterTemplate extends CreateRecord
{
    protected static string $resource = NewsletterTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => Auth::id(), 'updated_by' => Auth::id()];
    }

    protected function afterCreate(): void
    {
        app(AuditLogService::class)->record(Auth::user(), 'newsletter_template.created', $this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
