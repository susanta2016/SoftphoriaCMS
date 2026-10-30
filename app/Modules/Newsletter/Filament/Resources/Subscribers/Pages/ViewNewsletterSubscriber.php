<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Pages;

use App\Modules\Newsletter\Filament\Resources\Subscribers\NewsletterSubscriberResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsletterSubscriber extends ViewRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make(NewsletterSubscriberResource::subscriptionActions())
                ->label('Subscription')
                ->button(),
        ];
    }
}
