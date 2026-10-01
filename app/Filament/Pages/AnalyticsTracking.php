<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Analytics\AnalyticsIntegrations;
use App\Shared\Support\Analytics\AnalyticsSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Website Setup → Analytics & Tracking: the Google Analytics 4 Measurement
 * ID plus a master switch and "don't track admins". GA only loads after a
 * visitor accepts Tracking cookies in the cookie banner (see
 * AnalyticsIntegrations), and the banner lists it under that category.
 */
class AnalyticsTracking extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'website-setup/analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Analytics & Tracking';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(AnalyticsSettings::class)->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $ga4 = AnalyticsIntegrations::definitions()['ga4'];

        return $schema->components([
            Callout::make('Consent comes first')
                ->description(fn (): string => self::cookieBannerEnabled()
                    ? 'Google Analytics loads only after a visitor accepts Tracking cookies in the cookie banner, and stops if they withdraw consent. The cookie banner lists it automatically under Tracking cookies.'
                    : 'The cookie consent banner is switched off (Website Setup → Cookies Policy), so visitors cannot give consent — Google Analytics will not load until you switch the banner back on.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color(fn (): string => self::cookieBannerEnabled() ? 'info' : 'warning'),

            Section::make('General')
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('enabled')
                            ->label('Enable analytics & tracking')
                            ->helperText('Master switch. When off, Google Analytics is not loaded.'),
                        Toggle::make('exclude_admins')
                            ->label("Don't track logged-in admins")
                            ->helperText('Keeps your own visits out of the statistics.'),
                    ]),
                ]),

            Section::make('Google Analytics')
                ->description('Traffic and behaviour measurement. Cookie category: Tracking.')
                ->schema([
                    TextInput::make('ga4_id')
                        ->label('GA4 Measurement ID')
                        ->placeholder($ga4['example'])
                        ->maxLength(40)
                        ->regex($ga4['pattern'])
                        ->validationMessages(['regex' => "That doesn't look like a Google Analytics 4 Measurement ID (e.g. {$ga4['example']})."])
                        ->helperText('Google Analytics → Admin → Data streams → your web stream → Measurement ID. Leave empty to turn Google Analytics off.'),
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
        $settings = app(AnalyticsSettings::class);

        // Trim and upper-case a pasted ID before validation, so " g-abc123 " isn't rejected.
        $this->data['ga4_id'] = filled($this->data['ga4_id'] ?? null) ? strtoupper(trim($this->data['ga4_id'])) : null;

        $state = $this->form->getState();

        $settings->save($state);

        if ($entity = Setting::query()->where('group', AnalyticsSettings::GROUP)->first()) {
            app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, [
                'group' => AnalyticsSettings::GROUP,
                'keys' => array_keys($state),
            ]);
        }

        Notification::make()->title('Analytics settings saved')->success()->send();

        $this->form->fill($settings->all());
    }

    private static function cookieBannerEnabled(): bool
    {
        return (bool) app(SettingsRepository::class)->get('cookies', 'enabled', config('cookies_policy.enabled', true));
    }
}
