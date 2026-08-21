<?php

namespace App\Modules\Commerce\Filament\Pages;

use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
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
 * §7/§14 of the approved brief: guest/registered-free-member download
 * limits, configurable without a database redesign. Sibling to Website
 * Setup's existing Global Pricing (App\Filament\Pages\GlobalPricing) — same
 * `settings`-table pattern via SettingsRepository, but its own settings
 * *group* (`downloads`), never touching the `pricing` group Global Pricing
 * owns. Pro Member access is never configured here — it's always unlimited
 * while Subscription::isActive(), by definition (§4/§12), not a number an
 * admin sets.
 */
class DownloadAccessSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'website-setup/download-access';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static ?string $title = 'Download Access';

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
        return $schema->components([
            Section::make('Guest purchases')
                ->description('Applies to a Single/Album bought without an account.')
                ->schema([
                    TextInput::make('guest_max_downloads')
                        ->label('Maximum downloads')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('guest_expiry_days')
                        ->label('Access expires after (days)')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ]),
            Section::make('Registered free-member purchases')
                ->description('Applies to a Single/Album bought by a signed-in, non-subscribed user.')
                ->schema([
                    TextInput::make('member_max_downloads')
                        ->label('Maximum downloads')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('member_expiry_days')
                        ->label('Access expires after (days)')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ]),
        ]);
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

        $settings->set('downloads', 'guest_max_downloads', $state['guest_max_downloads'], 'integer');
        $settings->set('downloads', 'guest_expiry_days', $state['guest_expiry_days'], 'integer');
        $settings->set('downloads', 'member_max_downloads', $state['member_max_downloads'], 'integer');
        $settings->set('downloads', 'member_expiry_days', $state['member_expiry_days'], 'integer');

        $this->recordAudit(array_keys($state));

        Notification::make()->title('Download Access settings saved')->success()->send();

        $this->form->fill($this->loadFormState());
    }

    /**
     * @param  array<int, string>  $changedKeys
     */
    private function recordAudit(array $changedKeys): void
    {
        $entity = Setting::query()->where('group', 'downloads')->first();

        if (! $entity) {
            return;
        }

        app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, [
            'group' => 'downloads',
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
            'guest_max_downloads' => $settings->get('downloads', 'guest_max_downloads', 5),
            'guest_expiry_days' => $settings->get('downloads', 'guest_expiry_days', 30),
            'member_max_downloads' => $settings->get('downloads', 'member_max_downloads', 10),
            'member_expiry_days' => $settings->get('downloads', 'member_expiry_days', 90),
        ];
    }
}
