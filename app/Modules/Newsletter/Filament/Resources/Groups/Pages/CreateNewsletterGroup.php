<?php

namespace App\Modules\Newsletter\Filament\Resources\Groups\Pages;

use App\Modules\Newsletter\Filament\Resources\Groups\NewsletterGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterGroup extends CreateRecord
{
    protected static string $resource = NewsletterGroupResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
