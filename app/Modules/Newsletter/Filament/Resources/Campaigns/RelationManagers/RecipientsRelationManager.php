<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\RelationManagers;

use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\ViewNewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-recipient delivery status, read-only, on the campaign page once the
 * recipient snapshot exists.
 */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Recipients';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewNewsletterCampaign::class
            && $ownerRecord instanceof NewsletterCampaign
            && $ownerRecord->recipients_snapshot_at !== null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('status_reason')->label('Note')->limit(50)->placeholder('—')->toggleable(),
                TextColumn::make('sent_at')->label('Sent')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('delivered_at')->label('Delivered')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('opened_at')->label('First open')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('click_count')->label('Clicks')->numeric()->toggleable(),
                TextColumn::make('attempts')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unsubscribed_at')->label('Unsubscribed')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(CampaignRecipientStatus::class),
                TernaryFilter::make('opened')->nullable()->attribute('opened_at')->label('Opened'),
                TernaryFilter::make('clicked')->nullable()->attribute('clicked_at')->label('Clicked'),
                TernaryFilter::make('unsubscribed')
                    ->label('Unsubscribed')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('unsubscribed_at'),
                        false: fn (Builder $query) => $query->whereNull('unsubscribed_at'),
                    ),
            ])
            ->defaultSort('id')
            ->paginationPageOptions([25, 50, 100]);
    }
}
