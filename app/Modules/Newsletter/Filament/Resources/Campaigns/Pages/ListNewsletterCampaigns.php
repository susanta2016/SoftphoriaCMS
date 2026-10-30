<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Pages;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListNewsletterCampaigns extends ListRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New campaign'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $statuses = fn (CampaignStatus ...$statuses): array => array_map(fn (CampaignStatus $status): string => $status->value, $statuses);

        return [
            'all' => Tab::make('All'),
            'draft' => Tab::make('Drafts')->modifyQueryUsing(fn (Builder $query) => $query->where('status', CampaignStatus::Draft->value)),
            'in_progress' => Tab::make('Scheduled & sending')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses(...CampaignStatus::active()))),
            'sent' => Tab::make('Sent')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses(CampaignStatus::Sent, CampaignStatus::PartiallySent))),
            'failed' => Tab::make('Failed & cancelled')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses(CampaignStatus::Failed, CampaignStatus::Cancelled))),
        ];
    }
}
