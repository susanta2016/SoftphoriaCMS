<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Modules\Newsletter\Actions\Subscribers\ManageSubscriptionAction;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Concerns\GatedByNewsletterFeature;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\CreateNewsletterSubscriber;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\EditNewsletterSubscriber;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\ListNewsletterSubscribers;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\ViewNewsletterSubscriber;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Schemas\NewsletterSubscriberForm;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Schemas\NewsletterSubscriberInfolist;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Tables\NewsletterSubscribersTable;
use App\Modules\Newsletter\Support\SuppressionList;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Newsletter Marketing > Subscribers — the same newsletter_subscribers
 * table the footer signup writes. Profile and group membership are edited
 * through the form; whether someone may be emailed (consent, unsubscribe,
 * suppression) only through subscriptionActions(), which go through
 * ManageSubscriptionAction's rules and audit log. The email address can't
 * be changed after creation, so an address can't be swapped to sidestep a
 * suppression.
 */
class NewsletterSubscriberResource extends Resource
{
    use GatedByNewsletterFeature;

    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|UnitEnum|null $navigationGroup = 'Newsletter Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'newsletter/subscribers';

    protected static ?string $navigationLabel = 'Subscribers';

    protected static ?string $modelLabel = 'subscriber';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return NewsletterSubscriberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterSubscriberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscribersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscribers::route('/'),
            'create' => CreateNewsletterSubscriber::route('/create'),
            'view' => ViewNewsletterSubscriber::route('/{record}'),
            'edit' => EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public static function subscriptionActions(): array
    {
        $blocked = fn (NewsletterSubscriber $record): bool => app(SuppressionList::class)->blocksResubscribe($record->email);

        return [
            Action::make('unsubscribe')
                ->label('Unsubscribe')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->visible(fn (NewsletterSubscriber $record): bool => in_array($record->status, [SubscriberStatus::Subscribed->value, SubscriberStatus::Pending->value], true))
                ->requiresConfirmation()
                ->modalDescription('They will stop receiving newsletters immediately. To send to them again you will need their consent.')
                ->action(self::run(fn (ManageSubscriptionAction $manage, NewsletterSubscriber $record, User $admin) => $manage->unsubscribe($record, $admin), 'Unsubscribed.')),

            Action::make('resubscribe')
                ->label('Resubscribe')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (NewsletterSubscriber $record): bool => $record->status === SubscriberStatus::Unsubscribed->value && ! $blocked($record))
                ->modalDescription('Only resubscribe someone who has asked to receive the newsletter again. Their consent is recorded with your note.')
                ->schema([
                    Textarea::make('consent_note')
                        ->label('How did they give consent again?')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(self::run(fn (ManageSubscriptionAction $manage, NewsletterSubscriber $record, User $admin, array $data) => $manage->resubscribe($record, $admin, (string) $data['consent_note']), 'Resubscribed.')),

            Action::make('suppress')
                ->label('Suppress')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color('danger')
                ->visible(fn (NewsletterSubscriber $record): bool => ! $blocked($record))
                ->modalDescription('Adds this address to the global suppression list: it will never be emailed, whatever group it is in, until the suppression is lifted.')
                ->schema([
                    Textarea::make('note')->label('Reason (optional)')->maxLength(250),
                ])
                ->action(self::run(fn (ManageSubscriptionAction $manage, NewsletterSubscriber $record, User $admin, array $data) => $manage->suppress($record, $admin, $data['note'] ?? null), 'Suppressed.')),

            Action::make('liftSuppression')
                ->label('Lift suppression')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->visible($blocked)
                ->modalDescription('Removes the address from the suppression list. They stay Unsubscribed — resubscribe them separately once they have consented. Never lift a hard bounce or spam complaint without a good reason.')
                ->schema([
                    Textarea::make('reason')->label('Why is it safe to lift?')->required()->maxLength(500),
                ])
                ->action(self::run(fn (ManageSubscriptionAction $manage, NewsletterSubscriber $record, User $admin, array $data) => $manage->liftSuppression($record, $admin, (string) $data['reason']), 'Suppression lifted.')),
        ];
    }

    /**
     * Runs a ManageSubscriptionAction call for a Filament action, turning a
     * refusal into an error notification.
     */
    private static function run(Closure $callback, string $success): Closure
    {
        return function (NewsletterSubscriber $record, array $data, Action $action) use ($callback, $success): void {
            /** @var User $admin */
            $admin = Auth::user();

            try {
                $callback(app(ManageSubscriptionAction::class), $record, $admin, $data);
            } catch (NewsletterException $exception) {
                Notification::make()->title($exception->getMessage())->danger()->send();
                $action->halt();

                return;
            }

            $record->refresh();
            Notification::make()->title($success)->success()->send();
        };
    }
}
