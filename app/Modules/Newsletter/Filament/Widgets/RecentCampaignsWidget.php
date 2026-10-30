<?php

namespace App\Modules\Newsletter\Filament\Widgets;

use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Support\NewsletterFeature;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Recent campaign activity on the Newsletter Marketing dashboard.
 */
class RecentCampaignsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent campaign activity';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return NewsletterFeature::enabled();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NewsletterCampaign::query()->latest('updated_at')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->description(fn (NewsletterCampaign $record): string => $record->subject),
                TextColumn::make('status')->badge(),
                TextColumn::make('scheduled_at')
                    ->label('Scheduled / sent')
                    ->state(fn (NewsletterCampaign $record): ?string => $record->scheduledAtLocal()?->format('M j, Y g:i A T'))
                    ->placeholder('—'),
                TextColumn::make('recipients_total')->label('Recipients')->numeric(),
                TextColumn::make('updated_at')->label('Last activity')->since(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->url(fn (NewsletterCampaign $record): string => NewsletterCampaignResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No campaigns yet');
    }
}
