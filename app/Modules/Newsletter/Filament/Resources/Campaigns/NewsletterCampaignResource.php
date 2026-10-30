<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns;

use App\Models\User;
use App\Modules\Newsletter\Actions\Campaigns\CancelCampaignAction;
use App\Modules\Newsletter\Actions\Campaigns\DuplicateCampaignAction;
use App\Modules\Newsletter\Actions\Campaigns\PrepareCampaignContent;
use App\Modules\Newsletter\Actions\Campaigns\ScheduleCampaignAction;
use App\Modules\Newsletter\Actions\Campaigns\SendCampaignNowAction;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Concerns\GatedByNewsletterFeature;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\CreateNewsletterCampaign;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\EditNewsletterCampaign;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\ListCampaignReports;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\ListNewsletterCampaigns;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\ListScheduledCampaigns;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Pages\ViewNewsletterCampaign;
use App\Modules\Newsletter\Filament\Resources\Campaigns\RelationManagers\RecipientsRelationManager;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Schemas\NewsletterCampaignInfolist;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Tables\NewsletterCampaignsTable;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Support\NewsletterSettings;
use App\Modules\Newsletter\Support\RecipientResolver;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

use function Filament\Support\original_request;

/**
 * Newsletter Marketing > Campaigns (plus the Scheduled Campaigns and
 * Campaign Reports navigation items, which are filtered list pages of this
 * same resource). Only Drafts can be edited or deleted. Every send-related
 * button calls the matching App\Modules\Newsletter\Actions\Campaigns class,
 * which re-checks the module flag, the admin and the campaign's status
 * itself.
 */
class NewsletterCampaignResource extends Resource
{
    use GatedByNewsletterFeature;

    protected static ?string $model = NewsletterCampaign::class;

    protected static string|UnitEnum|null $navigationGroup = 'Newsletter Marketing';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'newsletter/campaigns';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $modelLabel = 'campaign';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        // The wizard steps come from NewsletterCampaignForm::steps() (the
        // Create/Edit pages use HasWizard).
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterCampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterCampaignsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof NewsletterCampaign && $record->status->isEditable() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof NewsletterCampaign && $record->status === CampaignStatus::Draft && parent::canDelete($record);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterCampaigns::route('/'),
            'scheduled' => ListScheduledCampaigns::route('/scheduled'),
            'reports' => ListCampaignReports::route('/reports'),
            'create' => CreateNewsletterCampaign::route('/create'),
            'view' => ViewNewsletterCampaign::route('/{record}'),
            'edit' => EditNewsletterCampaign::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $base = static::getRouteBaseName();

        return [
            NavigationItem::make('Campaigns')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedMegaphone)
                ->sort(5)
                ->isActiveWhen(fn (): bool => original_request()->routeIs("{$base}.index", "{$base}.create", "{$base}.view", "{$base}.edit"))
                ->url(static::getUrl('index')),
            NavigationItem::make('Scheduled Campaigns')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedClock)
                ->sort(6)
                ->badge(fn (): ?string => ($count = NewsletterCampaign::query()->where('status', CampaignStatus::Scheduled->value)->count()) > 0 ? (string) $count : null)
                ->isActiveWhen(fn (): bool => original_request()->routeIs("{$base}.scheduled"))
                ->url(static::getUrl('scheduled')),
            NavigationItem::make('Campaign Reports')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedChartBar)
                ->sort(7)
                ->isActiveWhen(fn (): bool => original_request()->routeIs("{$base}.reports"))
                ->url(static::getUrl('reports')),
        ];
    }

    public static function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label('Schedule')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->visible(fn (NewsletterCampaign $record): bool => $record->status === CampaignStatus::Draft)
            ->modalHeading('Schedule campaign')
            ->modalSubmitActionLabel('Schedule')
            ->fillForm(fn (NewsletterCampaign $record): array => [
                'date' => now(app(NewsletterSettings::class)->timezone())->addDay()->toDateString(),
                'time' => '09:00',
                'timezone' => $record->timezone ?: app(NewsletterSettings::class)->timezone(),
            ])
            ->schema([
                View::make('newsletter-marketing::filament.confirm-summary')
                    ->viewData(fn (NewsletterCampaign $record): array => self::summary($record)),
                DatePicker::make('date')->required()->native(false)->minDate(now()->subDay()),
                TimePicker::make('time')->required()->seconds(false),
                Select::make('timezone')
                    ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->required(),
            ])
            ->action(self::run(
                fn (NewsletterCampaign $record, User $admin, array $data) => app(ScheduleCampaignAction::class)
                    ->handle($record, $admin, $data['date'].' '.$data['time'], (string) $data['timezone']),
                fn (NewsletterCampaign $campaign): string => 'Scheduled for '.$campaign->scheduledAtLocal()?->format('M j, Y g:i A T').'.',
            ));
    }

    public static function sendNowAction(): Action
    {
        return Action::make('sendNow')
            ->label('Send now')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->visible(fn (NewsletterCampaign $record): bool => in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true))
            ->modalHeading('Send this campaign now?')
            ->modalDescription('It starts sending within a minute, in the background. This can\'t be undone, only cancelled part-way.')
            ->modalSubmitActionLabel('Send now')
            ->schema([
                View::make('newsletter-marketing::filament.confirm-summary')
                    ->viewData(fn (NewsletterCampaign $record): array => self::summary($record)),
            ])
            ->action(self::run(
                fn (NewsletterCampaign $record, User $admin) => app(SendCampaignNowAction::class)->handle($record, $admin),
                fn (): string => 'Campaign queued — sending has started.',
            ));
    }

    public static function unscheduleAction(): Action
    {
        return Action::make('unschedule')
            ->label('Unschedule')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (NewsletterCampaign $record): bool => $record->status === CampaignStatus::Scheduled)
            ->requiresConfirmation()
            ->modalDescription('Returns the campaign to Draft so it can be edited. Schedule it again when you are done.')
            ->action(self::run(
                fn (NewsletterCampaign $record, User $admin) => app(CancelCampaignAction::class)->unschedule($record, $admin),
                fn (): string => 'Back to draft.',
            ));
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel campaign')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (NewsletterCampaign $record): bool => in_array($record->status, CampaignStatus::active(), true))
            ->requiresConfirmation()
            ->modalDescription('Nothing more will be sent. Emails already sent stay in the report. A cancelled campaign can\'t be restarted — duplicate it instead.')
            ->action(self::run(
                fn (NewsletterCampaign $record, User $admin) => app(CancelCampaignAction::class)->handle($record, $admin),
                fn (): string => 'Campaign cancelled.',
            ));
    }

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->action(function (NewsletterCampaign $record, Action $action): void {
                try {
                    $copy = app(DuplicateCampaignAction::class)->handle($record, Auth::user());
                } catch (NewsletterException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->title('Duplicated as a new draft.')->success()->send();
                $action->redirect(static::getUrl('edit', ['record' => $copy]));
            });
    }

    /**
     * @return array{rows: array<string, string>, problems: array<int, string>}
     */
    private static function summary(NewsletterCampaign $campaign): array
    {
        $included = $campaign->includedGroups()->pluck('name')->all();
        $excluded = $campaign->excludedGroups()->pluck('name')->all();

        return [
            'rows' => [
                'Campaign' => $campaign->name,
                'Subject' => $campaign->subject,
                'From' => "{$campaign->from_name} <{$campaign->from_email}>",
                'Template' => $campaign->template?->name ?? '—',
                'Groups' => $included !== [] ? implode(', ', $included) : '—',
                'Excluding' => $excluded !== [] ? implode(', ', $excluded) : 'None',
                'Eligible recipients' => number_format(app(RecipientResolver::class)->countForCampaign($campaign)),
                ...($campaign->scheduled_at !== null ? ['Scheduled for' => $campaign->scheduledAtLocal()?->format('M j, Y g:i A T')] : []),
            ],
            'problems' => app(PrepareCampaignContent::class)->problems($campaign),
        ];
    }

    /**
     * @param  Closure(NewsletterCampaign, User, array<string, mixed>): NewsletterCampaign  $callback
     * @param  Closure(NewsletterCampaign): string  $success
     */
    private static function run(Closure $callback, Closure $success): Closure
    {
        return function (NewsletterCampaign $record, array $data, Action $action) use ($callback, $success): void {
            /** @var User $admin */
            $admin = Auth::user();

            try {
                $campaign = $callback($record, $admin, $data);
            } catch (NewsletterException $exception) {
                Notification::make()->title($exception->getMessage())->danger()->send();
                $action->halt();

                return;
            }

            $record->refresh();
            Notification::make()->title($success($campaign))->success()->send();
        };
    }
}
