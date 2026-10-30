<?php

namespace App\Modules\Newsletter\Filament\Resources\Groups\Pages;

use App\Modules\Newsletter\Filament\Resources\Groups\NewsletterGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterGroup extends EditRecord
{
    protected static string $resource = NewsletterGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NewsletterGroupResource::deleteAction(DeleteAction::make()),
        ];
    }
}
