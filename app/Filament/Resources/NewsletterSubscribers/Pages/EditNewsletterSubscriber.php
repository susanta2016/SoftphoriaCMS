<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterSubscriber extends EditRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['status'] === 'unsubscribed') {
            $data['unsubscribed_at'] = now();
        } elseif ($data['status'] === 'subscribed' && $this->record?->status !== 'subscribed') {
            $data['consented_at'] = now();
            $data['unsubscribed_at'] = null;
        }

        return $data;
    }
}
