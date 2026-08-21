<?php

namespace App\Modules\Commerce\Filament\Resources\Subscriptions\Pages;

use App\Modules\Commerce\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
