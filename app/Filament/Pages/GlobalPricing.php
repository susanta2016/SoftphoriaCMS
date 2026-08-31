<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Website Setup's Global Pricing — the site-wide prices shown wherever
 * Music content is sold (per-song, full-album) and where a Pro Member
 * subscription is offered. Not a Resource — a single Filament Page storing
 * everything as rows in the existing `settings` table via
 * SettingsRepository, same pattern as Settings/Cookies Policy. Amounts are
 * stored as plain decimal strings (e.g. "0.99") and only cast to float by
 * whatever later feature actually charges these prices.
 */
class GlobalPricing extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'website-setup/global-pricing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $title = 'Global Pricing';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadFormState());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(array_filter([
            Section::make('Music')
                ->description('Prices shown wherever a track or album can be purchased.')
                ->schema([
                    TextInput::make('music_per_song_price')
                        ->label('Per Song')
                        ->prefix('$')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required(),
                    TextInput::make('full_album_price')
                        ->label('Full Album')
                        ->prefix('$')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required(),
                ]),
            // Phase 1: no paid membership — hidden, not deleted, so Phase 2
            // just flips config('features.member_subscription_enabled')
            // back on. See save()'s matching guard below.
            config('features.member_subscription_enabled')
                ? Section::make('Membership')
                    ->schema([
                        TextInput::make('pro_member_monthly_price')
                            ->label('Pro Member')
                            ->prefix('$')
                            ->suffix('/ month')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                        Textarea::make('pro_member_cancellation_note')
                            ->label('Cancellation Information')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Shown to visitors alongside the "Become a Pro Member" registration option.')
                            ->columnSpanFull(),
                    ])
                : null,
        ]));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = app(SettingsRepository::class);

        $settings->set('pricing', 'music_per_song_price', $state['music_per_song_price']);
        $settings->set('pricing', 'full_album_price', $state['full_album_price']);

        // The Membership section isn't in $state at all when the flag is
        // off (form() never built it) — leave the stored values untouched
        // rather than error on a missing key or silently wipe them.
        if (config('features.member_subscription_enabled')) {
            $settings->set('pricing', 'pro_member_monthly_price', $state['pro_member_monthly_price']);
            $settings->set('pricing', 'pro_member_cancellation_note', $state['pro_member_cancellation_note']);
        }

        $this->recordAudit(array_keys($state));

        Notification::make()->title('Global Pricing saved')->success()->send();

        $this->form->fill($this->loadFormState());
    }

    /**
     * @param  array<int, string>  $changedKeys
     */
    private function recordAudit(array $changedKeys): void
    {
        $entity = Setting::query()->where('group', 'pricing')->first();

        if (! $entity) {
            return;
        }

        app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, [
            'group' => 'pricing',
            'keys' => $changedKeys,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormState(): array
    {
        $settings = app(SettingsRepository::class);

        return [
            'music_per_song_price' => $settings->get('pricing', 'music_per_song_price', '0.99'),
            'full_album_price' => $settings->get('pricing', 'full_album_price', '9.99'),
            'pro_member_monthly_price' => $settings->get('pricing', 'pro_member_monthly_price', '7.99'),
            'pro_member_cancellation_note' => $settings->get(
                'pricing',
                'pro_member_cancellation_note',
                'If you cancel, your Pro membership stays active until the end of your current billing period — you will not lose access immediately.',
            ),
        ];
    }
}
