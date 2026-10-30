<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Pages;

use App\Modules\Newsletter\Filament\Resources\Subscribers\NewsletterSubscriberResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterSubscriber extends EditRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Profile fields only — the address and anything about consent never
     * change through this form, even if posted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = array_intersect_key($data, array_flip(['first_name', 'last_name', 'profile_fields']));
        $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $this->getRecord()->name;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
