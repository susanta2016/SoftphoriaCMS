<?php

namespace App\Modules\Newsletter\Filament\Resources\Groups\Pages;

use App\Modules\Newsletter\Filament\Resources\Groups\NewsletterGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterGroups extends ListRecords
{
    protected static string $resource = NewsletterGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
