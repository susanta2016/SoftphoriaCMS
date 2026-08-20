<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Website Setup's Cookies Policy manager — the front-end consent banner and
 * Cookies Preferences Center (docs/Cookies Policy popup.docx) are entirely
 * copy-driven from here, stored as `cookies` group rows in the existing
 * `settings` table via SettingsRepository, same pattern as Settings' own
 * General/Footer/Email tabs. Every default below is the exact copy from the
 * approved screenshots, so an admin who never opens this page still gets a
 * fully-worded banner.
 */
class CookiesPolicy extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'website-setup/cookies-policy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $title = 'Cookies Policy';

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
            Tabs::make('CookiesPolicyTabs')
                ->tabs([
                    Tab::make('Banner')
                        ->schema($this->bannerTabSchema()),
                    Tab::make('Your Privacy')
                        ->schema($this->categoryTabSchema('privacy')),
                    Tab::make('Strictly Necessary')
                        ->schema($this->categoryTabSchema('necessary', alwaysActive: true)),
                    Tab::make('Functionality')
                        ->schema($this->categoryTabSchema('functionality')),
                    Tab::make('Tracking')
                        ->schema($this->categoryTabSchema('tracking')),
                    Tab::make('Targeting & Advertising')
                        ->schema($this->categoryTabSchema('targeting')),
                    Tab::make('More Information')
                        ->schema($this->moreInfoTabSchema()),
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

    /**
     * @return array<int, Component>
     */
    protected function bannerTabSchema(): array
    {
        return [
            Toggle::make('enabled')
                ->label('Show cookie banner on the public site')
                ->helperText('When off, the banner and Preferences Center never appear to visitors.'),
            TextInput::make('banner_title')
                ->label('Banner title')
                ->required()
                ->maxLength(255),
            Textarea::make('banner_description')
                ->label('Banner description')
                ->required()
                ->rows(3)
                ->maxLength(1000),
        ];
    }

    /**
     * Shared shape for the "Your Privacy" intro tab and each of the 4
     * cookie category tabs — a title + description, matching one screen of
     * the Preferences Center's right-hand panel each. Strictly Necessary is
     * always shown to visitors as an "Always active", un-toggleable switch
     * (docs/Cookies Policy popup.docx) — that behavior is fixed in the
     * public template, not admin-editable, so this form only ever collects
     * copy.
     *
     * @return array<int, Component>
     */
    protected function categoryTabSchema(string $prefix, bool $alwaysActive = false): array
    {
        return [
            TextInput::make("{$prefix}_title")
                ->label('Title')
                ->required()
                ->maxLength(255),
            Textarea::make("{$prefix}_description")
                ->label('Description')
                ->required()
                ->rows(4)
                ->maxLength(2000)
                ->helperText($alwaysActive
                    ? "Leave a blank line between paragraphs to start a new one on the public site. Visitors always see this category's toggle as \"Always active\" and cannot switch it off."
                    : 'Leave a blank line between paragraphs to start a new one on the public site.'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected function moreInfoTabSchema(): array
    {
        return [
            TextInput::make('more_info_title')
                ->label('Title')
                ->required()
                ->maxLength(255),
            Textarea::make('more_info_description')
                ->label('Description')
                ->required()
                ->rows(3)
                ->maxLength(1000)
                ->helperText('A "To find out more, please visit our Privacy Policy" line linking to the Privacy Policy page is always appended automatically on the public site.'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = app(SettingsRepository::class);

        $settings->set('cookies', 'enabled', (bool) $state['enabled'], 'boolean');
        $settings->set('cookies', 'banner_title', $state['banner_title']);
        $settings->set('cookies', 'banner_description', $state['banner_description']);
        $settings->set('cookies', 'privacy_title', $state['privacy_title']);
        $settings->set('cookies', 'privacy_description', $state['privacy_description']);
        $settings->set('cookies', 'necessary_title', $state['necessary_title']);
        $settings->set('cookies', 'necessary_description', $state['necessary_description']);
        $settings->set('cookies', 'functionality_title', $state['functionality_title']);
        $settings->set('cookies', 'functionality_description', $state['functionality_description']);
        $settings->set('cookies', 'tracking_title', $state['tracking_title']);
        $settings->set('cookies', 'tracking_description', $state['tracking_description']);
        $settings->set('cookies', 'targeting_title', $state['targeting_title']);
        $settings->set('cookies', 'targeting_description', $state['targeting_description']);
        $settings->set('cookies', 'more_info_title', $state['more_info_title']);
        $settings->set('cookies', 'more_info_description', $state['more_info_description']);

        $this->recordAudit(array_keys($state));

        Notification::make()->title('Cookies Policy saved')->success()->send();

        $this->form->fill($this->loadFormState());
    }

    /**
     * @param  array<int, string>  $changedKeys
     */
    private function recordAudit(array $changedKeys): void
    {
        $entity = Setting::query()->where('group', 'cookies')->first();

        if (! $entity) {
            return;
        }

        app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, [
            'group' => 'cookies',
            'keys' => $changedKeys,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormState(): array
    {
        $settings = app(SettingsRepository::class);
        $defaults = config('cookies_policy');

        return collect($defaults)
            ->map(fn (mixed $default, string $key): mixed => $settings->get(
                'cookies',
                $key,
                $key === 'enabled' ? (bool) $default : $default,
            ))
            ->all();
    }
}
