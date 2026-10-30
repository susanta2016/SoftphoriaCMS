<?php

namespace App\Modules\Newsletter\Filament\Pages;

use App\Models\Setting;
use App\Modules\Newsletter\Filament\Concerns\GatedByNewsletterFeature;
use App\Modules\Newsletter\Support\NewsletterSettings;
use App\Modules\Newsletter\Support\SenderIdentity;
use App\Modules\Newsletter\Support\TemplateVariables;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Newsletter Marketing > Newsletter Settings — defaults for new campaigns,
 * stored in the shared settings table (group "newsletter") like every
 * other settings page. There is deliberately no "enable module" switch:
 * NEWSLETTER_MARKETING_ENABLED is the only one. Provider credentials stay
 * in the environment; this page only shows which mailer is in use.
 */
class NewsletterSettingsPage extends Page
{
    use GatedByNewsletterFeature;

    protected static string|UnitEnum|null $navigationGroup = 'Newsletter Marketing';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'newsletter/settings';

    protected static ?string $navigationLabel = 'Newsletter Settings';

    protected static ?string $title = 'Newsletter Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

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
        $ceiling = max(1, (int) config('newsletter.max_rate_per_second'));

        return $schema->components([
            Section::make('Sender')
                ->description('Defaults for new campaigns. Each campaign can change them.')
                ->columns(2)
                ->schema([
                    TextInput::make('from_name')->label('Default sender name')->required()->maxLength(100),
                    TextInput::make('from_email')
                        ->label('Default sender email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled(config('newsletter.allowed_sender_domains')) && ($problem = app(SenderIdentity::class)->validate((string) $value)) !== null) {
                                $fail($problem);
                            }
                        }]),
                    Textarea::make('sender_address')
                        ->label('Postal address')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Shown in the email footer via {{ sender.address }}. Anti-spam laws (e.g. CAN-SPAM) expect a physical address in marketing email.')
                        ->columnSpanFull(),
                ]),

            Section::make('Sending')
                ->columns(2)
                ->schema([
                    Select::make('timezone')
                        ->label('Default timezone')
                        ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->searchable()
                        ->required(),
                    TextInput::make('rate_per_second')
                        ->label('Emails per second')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue($ceiling)
                        ->required()
                        ->helperText("At most {$ceiling} (NEWSLETTER_MAX_RATE_PER_SECOND — keep it within your provider's sending limit)."),
                    TextInput::make('test_recipient')
                        ->label('Default test recipient')
                        ->email()
                        ->maxLength(255)
                        ->helperText('Pre-filled in "Send test email". Defaults to your own address.'),
                    TextEntry::make('mailer')
                        ->label('Delivery provider')
                        ->state(fn (): string => self::mailerDescription())
                        ->helperText('Set by NEWSLETTER_MAILER in the server environment, not here.'),
                ]),

            Section::make('Footer')
                ->schema([
                    Textarea::make('footer_html')
                        ->label('Default footer (HTML)')
                        ->rows(6)
                        ->required()
                        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! str_contains(preg_replace('/\s+/', '', (string) $value), '{{unsubscribe_url}}')) {
                                $fail('The footer must contain {{ unsubscribe_url }}.');
                            }

                            if (($unknown = TemplateVariables::unknownIn((string) $value)) !== []) {
                                $fail('Unknown placeholder(s): '.implode(', ', $unknown).'.');
                            }
                        }])
                        ->helperText('Used by {{ footer }}, and added automatically to any email whose template has no unsubscribe link. Must include {{ unsubscribe_url }}.'),
                ]),

            Section::make('Tracking defaults')
                ->description('Starting values for new campaigns. Open tracking is approximate; both are off unless you turn them on.')
                ->columns(2)
                ->schema([
                    Toggle::make('track_opens')->label('Track opens'),
                    Toggle::make('track_clicks')->label('Track clicks'),
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
        $group = NewsletterSettings::GROUP;

        foreach (['from_name', 'from_email', 'sender_address', 'timezone', 'test_recipient', 'footer_html'] as $key) {
            $settings->set($group, $key, $state[$key] ?? null);
        }

        $settings->set($group, 'rate_per_second', (int) $state['rate_per_second'], 'integer');
        $settings->set($group, 'track_opens', (bool) ($state['track_opens'] ?? false), 'boolean');
        $settings->set($group, 'track_clicks', (bool) ($state['track_clicks'] ?? false), 'boolean');

        $entity = Setting::query()->where('group', $group)->first();

        if ($entity !== null) {
            app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, ['group' => $group, 'keys' => array_keys($state)]);
        }

        Notification::make()->title('Newsletter settings saved')->success()->send();

        $this->form->fill($this->loadFormState());
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormState(): array
    {
        $newsletter = app(NewsletterSettings::class);

        return [
            'from_name' => $newsletter->fromName(),
            'from_email' => $newsletter->fromEmail(),
            'sender_address' => $newsletter->senderAddress(),
            'timezone' => $newsletter->timezone(),
            'rate_per_second' => $newsletter->ratePerSecond(),
            'test_recipient' => $newsletter->testRecipient(),
            'footer_html' => $newsletter->footerHtml(),
            'track_opens' => $newsletter->trackOpensByDefault(),
            'track_clicks' => $newsletter->trackClicksByDefault(),
        ];
    }

    private static function mailerDescription(): string
    {
        $configured = config('newsletter.mailer');

        if (blank($configured)) {
            return 'Site email settings (Website Setup > Settings > Email) — fine for testing; a dedicated provider is recommended for bulk sending.';
        }

        $transport = config("mail.mailers.{$configured}.transport", 'unknown');
        $host = config("mail.mailers.{$configured}.host");

        return "Mailer \"{$configured}\" ({$transport}".($host ? ", {$host}" : '').')';
    }
}
