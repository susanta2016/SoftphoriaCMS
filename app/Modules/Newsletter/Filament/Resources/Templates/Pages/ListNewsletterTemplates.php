<?php

namespace App\Modules\Newsletter\Filament\Resources\Templates\Pages;

use App\Modules\Newsletter\Filament\Resources\Templates\NewsletterTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterTemplates extends ListRecords
{
    protected static string $resource = NewsletterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
