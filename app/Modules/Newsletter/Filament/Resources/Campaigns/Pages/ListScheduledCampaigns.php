<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Pages;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Newsletter Marketing > Scheduled Campaigns: what is going out next,
 * in each campaign's own timezone, soonest first.
 */
class ListScheduledCampaigns extends ListRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected static ?string $title = 'Scheduled Campaigns';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CampaignStatus::Scheduled->value))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (NewsletterCampaign $record): string => $record->subject),
                TextColumn::make('scheduled_at')
                    ->label('Sends at')
                    ->state(fn (NewsletterCampaign $record): ?string => $record->scheduledAtLocal()?->format('D, M j, Y g:i A T'))
                    ->description(fn (NewsletterCampaign $record): ?string => $record->scheduled_at?->diffForHumans())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('scheduled_at', $direction)),
                TextColumn::make('template.name')->label('Template')->placeholder('—'),
                TextColumn::make('creator.name')->label('Scheduled by')->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
                NewsletterCampaignResource::unscheduleAction(),
                NewsletterCampaignResource::cancelAction(),
            ])
            ->defaultSort('scheduled_at')
            ->emptyStateHeading('Nothing scheduled')
            ->emptyStateDescription('Schedule a draft campaign from its page.');
    }
}
