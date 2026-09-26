<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Analytics\AnalyticsIntegrations;
use App\Shared\Support\Analytics\AnalyticsSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 * Website Setup → Analytics & Tracking: IDs for Google Analytics 4, Google
 * Tag Manager, Microsoft Clarity, Meta Pixel and LinkedIn Insight, search
 * engine verification codes, and optional custom code. Every tracking tool
 * loads only after the visitor consents to its cookie category (see
 * AnalyticsIntegrations), and is disclosed automatically on the Cookie and
 * Privacy Policy pages.
 */
class AnalyticsTracking extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 3;

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
        $definitions = AnalyticsIntegrations::definitions();
        $idField = fn (string $key, string $help): TextInput => TextInput::make($definitions[$key]['setting'])
            ->label($definitions[$key]['label'])
            ->placeholder($definitions[$key]['example'])
            ->maxLength(40)
            ->regex($definitions[$key]['pattern'])
            ->validationMessages(['regex' => "That doesn't look like a {$definitions[$key]['label']} ID (e.g. {$definitions[$key]['example']})."])
            ->helperText($help);

        return $schema->components([
            Callout::make('Consent comes first')
                ->description(fn (): string => app(SettingsRepository::class)->get('cookies', 'enabled', true)
                    ? 'Tracking tools load only after a visitor accepts their cookie category in the cookie banner, and stop if they withdraw consent. The tools you switch on here are listed automatically on your Cookie Policy and Privacy Policy pages.'
                    : 'The cookie consent banner is switched off (Website Setup → Cookies Policy), so visitors cannot give consent — no tracking tool will load until you switch it back on.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color(fn (): string => app(SettingsRepository::class)->get('cookies', 'enabled', true) ? 'info' : 'warning'),

            Section::make('General')
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('enabled')
                            ->label('Enable analytics & tracking')
                            ->helperText('Master switch. When off, no tracking tool is loaded or listed in your policies.'),
                        Toggle::make('exclude_admins')
                            ->label("Don't track logged-in admins")
                            ->helperText('Keeps your own visits out of the statistics.'),
                    ]),
                ]),

            Section::make('Analytics')
                ->description('Traffic and behaviour measurement. Cookie category: Tracking.')
                ->columns(2)
                ->schema([
                    $idField('ga4', 'Google Analytics → Admin → Data streams → your web stream → Measurement ID.'),
                    $idField('gtm', 'Use either Analytics or Tag Manager for Google tags — not the same tag in both.'),
                    $idField('clarity', 'Microsoft Clarity → Settings → Overview → Project ID.'),
                ]),

            Section::make('Advertising')
                ->description('Ad conversion tracking and audiences. Cookie category: Targeting and advertising.')
                ->columns(2)
                ->schema([
                    $idField('meta_pixel', 'Meta Events Manager → Data sources → your Pixel ID.'),
                    $idField('linkedin', 'LinkedIn Campaign Manager → Insight Tag → Partner ID.'),
                ]),

            Section::make('Search engine verification')
                ->description('Proves you own the site to Google Search Console and Bing Webmaster Tools. Sets no cookies, so it is always output.')
                ->columns(2)
                ->schema([
                    TextInput::make('google_site_verification')
                        ->label('Google Search Console')
                        ->placeholder('abc123…')
                        ->maxLength(100)
                        ->regex('/^[A-Za-z0-9_\-]+$/')
                        ->helperText('Only the content="…" value of the HTML-tag verification method.'),
                    TextInput::make('bing_site_verification')
                        ->label('Bing Webmaster Tools')
                        ->placeholder('ABCDEF0123456789…')
                        ->maxLength(100)
                        ->regex('/^[A-Za-z0-9_\-]+$/')
                        ->helperText('Only the content="…" value of the msvalidate.01 meta tag.'),
                ]),

            Section::make('Additional code')
                ->description('For any other tool. Paste the provider\'s snippet exactly; it loads only with consent to the category you choose.')
                ->collapsible()
                ->collapsed(fn ($get): bool => blank($get('custom_head_code')) && blank($get('custom_body_code')))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('custom_code_category')
                            ->label('Cookie category')
                            ->options(AnalyticsIntegrations::CATEGORY_LABELS)
                            ->native(false)
                            ->required(),
                        TextInput::make('custom_code_description')
                            ->label('What it does (shown in your Cookie Policy)')
                            ->placeholder('e.g. Hotjar — heatmaps to improve usability')
                            ->maxLength(200),
                    ]),
                    Textarea::make('custom_head_code')
                        ->label('Code for <head>')
                        ->rows(5)
                        ->maxLength(20000)
                        ->extraInputAttributes(['class' => 'font-mono text-xs']),
                    Textarea::make('custom_body_code')
                        ->label('Code for the end of <body>')
                        ->rows(4)
                        ->maxLength(20000)
                        ->extraInputAttributes(['class' => 'font-mono text-xs']),
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

        // Trim pasted IDs before validation, so " G-XXXX " isn't rejected.
        foreach (['ga4_id', 'gtm_id', 'clarity_id', 'meta_pixel_id', 'linkedin_partner_id', 'google_site_verification', 'bing_site_verification'] as $key) {
            $this->data[$key] = filled($this->data[$key] ?? null) ? trim($this->data[$key]) : null;
        }

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
}
