<?php

namespace App\Filament\Clusters\WebsiteSetup\Resources\EmailTemplates\Pages;

use App\Filament\Clusters\WebsiteSetup\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No CreateAction in the header — EmailTemplateResource::canCreate()
 * already returns false, but this class exists (rather than relying on the
 * default) so the intent is explicit and discoverable alongside the
 * Resource, per the "fixed, seeded registry" requirement.
 */
class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
