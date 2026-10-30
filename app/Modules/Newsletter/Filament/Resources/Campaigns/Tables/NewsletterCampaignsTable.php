<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Tables;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('template'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (NewsletterCampaign $record): string => $record->subject),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('template.name')->label('Template')->placeholder('—')->toggleable(),
                TextColumn::make('scheduled_at')
                    ->label('Scheduled / sent')
                    ->state(fn (NewsletterCampaign $record): ?string => $record->scheduledAtLocal()?->format('M j, Y g:i A T'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('scheduled_at', $direction))
                    ->placeholder('—'),
                TextColumn::make('recipients_total')->label('Recipients')->numeric()->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(CampaignStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('No campaigns yet')
            ->emptyStateDescription('Create a campaign, pick a template and groups, preview it, then schedule or send it.');
    }
}
