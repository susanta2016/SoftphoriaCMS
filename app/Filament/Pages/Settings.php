<?php

namespace App\Filament\Pages;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Models\Page as PageModel;
use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\MailSettingsApplier;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * Website Setup's General + Email Settings tabs (docs/ARCHITECTURE.md §16).
 * Not a Resource — a single Filament Page storing everything as rows in the
 * existing `settings` table via SettingsRepository, never a new table.
 */
class Settings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'website-setup/settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Settings';

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
            Tabs::make('WebsiteSetupTabs')
                ->tabs([
                    Tab::make('General')
                        ->schema($this->generalTabSchema()),
                    Tab::make('SEO')
                        ->schema($this->seoTabSchema()),
                    Tab::make('Footer')
                        ->schema($this->footerTabSchema()),
                    Tab::make('Podcast')
                        ->schema($this->podcastTabSchema()),
                    Tab::make('Email')
                        ->schema($this->emailTabSchema()),
                    Tab::make('Registration')
                        ->schema($this->registrationTabSchema()),
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
    protected function generalTabSchema(): array
    {
        return [
            TextInput::make('general.site_name')
                ->label('Site Name')
                ->required()
                ->maxLength(255),
            TextInput::make('general.tagline')
                ->label('Tagline')
                ->maxLength(255),
            TextInput::make('general.site_url')
                ->label('Site URL')
                ->url()
                ->required()
                ->maxLength(255),
            MediaPicker::make('general.logo_media_id', 'Logo', MediaCategory::Image),
            MediaPicker::make('general.favicon_media_id', 'Favicon', MediaCategory::Image),
            Toggle::make('general.maintenance_mode')
                ->label('Maintenance Mode')
                ->live()
                ->helperText('When on, every public request shows the selected Maintenance Page instead of the normal site. The admin area always stays reachable.'),
            Select::make('general.maintenance_page_id')
                ->label('Maintenance Page')
                ->options(fn (): array => PageModel::query()->published()->orderBy('title')->pluck('title', 'id')->all())
                ->searchable()
                ->helperText('Only published pages can be selected — reuses the existing Pages/CMS, never a separate maintenance content system.')
                ->required(fn (Get $get): bool => (bool) $get('general.maintenance_mode'))
                ->visible(fn (Get $get): bool => (bool) $get('general.maintenance_mode')),
        ];
    }

    /**
     * Site-wide identifiers used on every public page's <head>
     * (App\Shared\Support\Seo\SeoTagBuilder) that don't belong on a
     * per-page SeoMetadata row — Twitter Card's twitter:site/creator,
     * Open Graph's fb:app_id, and the og:image/twitter:image fallback for
     * any page that hasn't set its own social image.
     *
     * @return array<int, Component>
     */
    protected function seoTabSchema(): array
    {
        return [
            MediaPicker::make('general.default_share_image_media_id', 'Default Social Share Image', MediaCategory::Image)
                ->columnSpanFull(),
            TextInput::make('general.twitter_handle')
                ->label('Twitter/X Handle')
                ->placeholder('allthethingslight')
                ->maxLength(255)
                ->helperText('Without the @ — used for the twitter:site and twitter:creator tags on every page.'),
            TextInput::make('general.fb_app_id')
                ->label('Facebook App ID')
                ->maxLength(255)
                ->helperText('Optional — only needed if this site is linked to a Facebook app.'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected function footerTabSchema(): array
    {
        return [
            MediaPicker::make('footer.logo_media_id', 'Footer Logo', MediaCategory::Image),
            TextInput::make('footer.subheading')
                ->label('Sub-heading text')
                ->maxLength(255)
                ->helperText('Shown under the logo in the footer, e.g. "A creative home for music, writing, reflection, thinking, and community."'),
            MediaPicker::make('footer.background_media_id', 'Footer Background Image', MediaCategory::Image),
            TextInput::make('footer.copyright_text')
                ->label('Copyright Text')
                ->maxLength(255)
                ->helperText('Shown at the bottom of the footer. Use {year} anywhere in the text and it will always be replaced with the current year, e.g. "© {year} All The Things Light. All rights reserved."'),
        ];
    }

    /**
     * The public Podcast landing page's hero banner image — its own setting
     * group (not a `podcasts` row column) since it's a single site-wide
     * visual for the whole Podcast section, independent of any one Podcast
     * show, exactly like Footer's own background_media_id above.
     *
     * @return array<int, Component>
     */
    protected function podcastTabSchema(): array
    {
        return [
            MediaPicker::make('podcast.hero_banner_media_id', 'Podcast Hero Banner', MediaCategory::Image),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected function emailTabSchema(): array
    {
        return [
            Toggle::make('email.enabled')
                ->label('Enable Email Sending')
                ->live()
                ->helperText('When off, mail is composed and logged, never actually delivered.'),
            Select::make('email.provider')
                ->label('Provider')
                ->options(['smtp' => 'SMTP'])
                ->default('smtp')
                ->required(),
            TextInput::make('email.smtp_host')
                ->label('Host')
                ->maxLength(255),
            TextInput::make('email.smtp_port')
                ->label('Port')
                ->numeric()
                ->maxLength(10),
            Select::make('email.smtp_encryption')
                ->label('Encryption')
                ->options(['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'])
                ->default('tls'),
            TextInput::make('email.smtp_username')
                ->label('Username')
                ->maxLength(255),
            TextInput::make('email.smtp_password')
                ->label('Password')
                ->password()
                ->revealable()
                ->maxLength(255)
                ->helperText('Leave blank to keep the currently saved password. Never displayed once saved.'),
            TextInput::make('email.from_name')
                ->label('Sender Name')
                ->maxLength(255),
            TextInput::make('email.from_email')
                ->label('Sender Email')
                ->email()
                ->maxLength(255),
            TextInput::make('email.reply_to_name')
                ->label('Reply-To Name')
                ->maxLength(255),
            TextInput::make('email.reply_to_email')
                ->label('Reply-To Email')
                ->email()
                ->maxLength(255),
            TextInput::make('email.test_recipient_email')
                ->label('Test Recipient Email')
                ->email()
                ->maxLength(255),
            Actions::make([$this->sendTestEmailAction()])->key('sendTestEmailActions'),
        ];
    }

    /**
     * The confirmation copy shown on the Free/Pro registration thank-you
     * pages (App\Http\Controllers\RegistrationController) — admin-controlled
     * per the confirmed spec, never hardcoded. Reuses this existing
     * settings/SettingsRepository mechanism rather than a new one.
     *
     * @return array<int, Component>
     */
    protected function registrationTabSchema(): array
    {
        return [
            Textarea::make('registration.free_confirmation_message')
                ->label('Free Registration Confirmation Message')
                ->rows(3)
                ->maxLength(1000)
                ->helperText('Shown on the thank-you page after a Free registration.'),
            Textarea::make('registration.pro_confirmation_message')
                ->label('Pro Registration Confirmation Message')
                ->rows(3)
                ->maxLength(1000)
                ->helperText('Shown on the thank-you page only once Stripe confirms the Pro Member payment/subscription.'),
        ];
    }

    /**
     * Reads live, unsaved form values first (Get $get) and falls back to the
     * saved configuration for any field left blank — so testing works
     * against an edited-but-unsaved configuration as well as a previously
     * saved one, per the approved requirement. Always attempts a real send
     * regardless of the "Enable Email Sending" toggle's current state,
     * since that is the point of a test.
     */
    protected function sendTestEmailAction(): Action
    {
        return Action::make('sendTestEmail')
            ->label('Send Test Email')
            ->color('gray')
            ->action(function (Get $get): void {
                $to = $get('email.test_recipient_email');

                if (blank($to)) {
                    Notification::make()->title('Enter a test recipient email first.')->danger()->send();

                    return;
                }

                $saved = app(SettingsRepository::class)->all('email');
                $pick = fn (string $key): mixed => filled($get("email.{$key}")) ? $get("email.{$key}") : ($saved[$key] ?? null);

                app(MailSettingsApplier::class)->applyFromArray([
                    'enabled' => true,
                    'smtp_host' => $pick('smtp_host'),
                    'smtp_port' => $pick('smtp_port'),
                    'smtp_encryption' => $pick('smtp_encryption'),
                    'smtp_username' => $pick('smtp_username'),
                    'smtp_password' => $pick('smtp_password'),
                    'from_name' => $pick('from_name'),
                    'from_email' => $pick('from_email'),
                ]);

                $replyToEmail = $pick('reply_to_email');
                $replyToName = $pick('reply_to_name');

                try {
                    Mail::raw(
                        'This is a test email sent from Website Setup to confirm the current Email Settings configuration.',
                        function ($message) use ($to, $replyToEmail, $replyToName): void {
                            $message->to($to)->subject('Softphoria — Test Email');

                            if (filled($replyToEmail)) {
                                $message->replyTo($replyToEmail, $replyToName ?: null);
                            }
                        },
                    );

                    Notification::make()->title("Test email sent to {$to}")->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Test email failed to send')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = app(SettingsRepository::class);

        $general = $state['general'];
        $settings->set('general', 'site_name', $general['site_name']);
        $settings->set('general', 'tagline', $general['tagline']);
        $settings->set('general', 'site_url', $general['site_url']);
        $settings->set('general', 'logo_media_id', $general['logo_media_id'], 'integer');
        $settings->set('general', 'favicon_media_id', $general['favicon_media_id'], 'integer');
        $settings->set('general', 'maintenance_mode', (bool) $general['maintenance_mode'], 'boolean');
        $settings->set('general', 'default_share_image_media_id', $general['default_share_image_media_id'], 'integer');
        $settings->set('general', 'twitter_handle', $general['twitter_handle']);
        $settings->set('general', 'fb_app_id', $general['fb_app_id']);

        // The Maintenance Page field is only visible (and therefore only
        // dehydrated into form state) while Maintenance Mode is on — when
        // it's hidden, leave the previously saved selection untouched
        // rather than wiping it, so toggling the mode back on later still
        // shows the admin's last choice.
        if (array_key_exists('maintenance_page_id', $general)) {
            $settings->set('general', 'maintenance_page_id', $general['maintenance_page_id'], 'integer');
        }

        $footer = $state['footer'];
        $settings->set('footer', 'logo_media_id', $footer['logo_media_id'], 'integer');
        $settings->set('footer', 'subheading', $footer['subheading']);
        $settings->set('footer', 'background_media_id', $footer['background_media_id'], 'integer');
        $settings->set('footer', 'copyright_text', $footer['copyright_text']);

        $podcast = $state['podcast'];
        $settings->set('podcast', 'hero_banner_media_id', $podcast['hero_banner_media_id'], 'integer');

        $email = $state['email'];
        $settings->set('email', 'enabled', (bool) $email['enabled'], 'boolean');
        $settings->set('email', 'provider', $email['provider']);
        $settings->set('email', 'smtp_host', $email['smtp_host']);
        $settings->set('email', 'smtp_port', $email['smtp_port']);
        $settings->set('email', 'smtp_encryption', $email['smtp_encryption']);
        $settings->set('email', 'smtp_username', $email['smtp_username']);

        // Only overwrite the encrypted password if the admin typed a new
        // one — an intentionally blank field means "keep the saved value",
        // never "clear it", since the field is never prefilled (§16.2).
        if (filled($email['smtp_password'] ?? null)) {
            $settings->set('email', 'smtp_password', $email['smtp_password'], 'encrypted');
        }

        $settings->set('email', 'from_name', $email['from_name']);
        $settings->set('email', 'from_email', $email['from_email']);
        $settings->set('email', 'reply_to_name', $email['reply_to_name']);
        $settings->set('email', 'reply_to_email', $email['reply_to_email']);
        $settings->set('email', 'test_recipient_email', $email['test_recipient_email']);

        $registration = $state['registration'];
        $settings->set('registration', 'free_confirmation_message', $registration['free_confirmation_message']);
        $settings->set('registration', 'pro_confirmation_message', $registration['pro_confirmation_message']);

        $this->recordAudit('general', array_keys($general));
        $this->recordAudit('footer', array_keys($footer));
        $this->recordAudit('podcast', array_keys($podcast));
        // Never log the password value itself, even in metadata.
        $this->recordAudit('email', array_keys(array_diff_key($email, ['smtp_password' => true])));
        $this->recordAudit('registration', array_keys($registration));

        Notification::make()->title('Settings saved')->success()->send();

        $this->form->fill($this->loadFormState());
    }

    /**
     * @param  array<int, string>  $changedKeys
     */
    private function recordAudit(string $group, array $changedKeys): void
    {
        $entity = Setting::query()->where('group', $group)->first();

        if (! $entity) {
            return;
        }

        app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, [
            'group' => $group,
            'keys' => $changedKeys,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadFormState(): array
    {
        $settings = app(SettingsRepository::class);

        return [
            'general' => [
                'site_name' => $settings->get('general', 'site_name'),
                'tagline' => $settings->get('general', 'tagline'),
                'site_url' => $settings->get('general', 'site_url', config('app.url')),
                'logo_media_id' => $settings->get('general', 'logo_media_id'),
                'favicon_media_id' => $settings->get('general', 'favicon_media_id'),
                'maintenance_mode' => $settings->get('general', 'maintenance_mode', false),
                'maintenance_page_id' => $settings->get('general', 'maintenance_page_id'),
                'default_share_image_media_id' => $settings->get('general', 'default_share_image_media_id'),
                'twitter_handle' => $settings->get('general', 'twitter_handle'),
                'fb_app_id' => $settings->get('general', 'fb_app_id'),
            ],
            'footer' => [
                'logo_media_id' => $settings->get('footer', 'logo_media_id'),
                'subheading' => $settings->get('footer', 'subheading'),
                'background_media_id' => $settings->get('footer', 'background_media_id'),
                'copyright_text' => $settings->get('footer', 'copyright_text'),
            ],
            'podcast' => [
                'hero_banner_media_id' => $settings->get('podcast', 'hero_banner_media_id'),
            ],
            'email' => [
                'enabled' => $settings->get('email', 'enabled', false),
                'provider' => $settings->get('email', 'provider', 'smtp'),
                'smtp_host' => $settings->get('email', 'smtp_host'),
                'smtp_port' => $settings->get('email', 'smtp_port'),
                'smtp_encryption' => $settings->get('email', 'smtp_encryption', 'tls'),
                'smtp_username' => $settings->get('email', 'smtp_username'),
                'smtp_password' => null,
                'from_name' => $settings->get('email', 'from_name'),
                'from_email' => $settings->get('email', 'from_email'),
                'reply_to_name' => $settings->get('email', 'reply_to_name'),
                'reply_to_email' => $settings->get('email', 'reply_to_email'),
                'test_recipient_email' => $settings->get('email', 'test_recipient_email'),
            ],
            'registration' => [
                'free_confirmation_message' => $settings->get(
                    'registration',
                    'free_confirmation_message',
                    'Thank you for your registration! Please verify your registered email from your mailbox.',
                ),
                'pro_confirmation_message' => $settings->get(
                    'registration',
                    'pro_confirmation_message',
                    'Thank you for your registration and for becoming a Pro Member! Please verify your registered email from your mailbox.',
                ),
            ],
        ];
    }
}
