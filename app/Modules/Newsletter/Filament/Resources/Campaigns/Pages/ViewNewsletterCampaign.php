<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Pages;

use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Filament\Support\NewsletterPreviewActions;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The campaign page — step 5 of the workflow (preview, test, then schedule
 * or send) and, once sending starts, its delivery report.
 */
class ViewNewsletterCampaign extends ViewRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            NewsletterPreviewActions::preview(),
            NewsletterPreviewActions::sendTest()
                ->visible(fn (NewsletterCampaign $record): bool => $record->template !== null),
            NewsletterCampaignResource::scheduleAction(),
            NewsletterCampaignResource::sendNowAction(),
            ActionGroup::make([
                NewsletterCampaignResource::unscheduleAction(),
                NewsletterCampaignResource::duplicateAction(),
                NewsletterCampaignResource::cancelAction(),
                DeleteAction::make(),
            ]),
        ];
    }
}
