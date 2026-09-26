<?php

namespace App\Filament\Resources\ToolCategories\Pages;

use App\Filament\Resources\ToolCategories\ToolCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageToolCategories extends ManageRecords
{
    protected static string $resource = ToolCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Category'),
        ];
    }
}
