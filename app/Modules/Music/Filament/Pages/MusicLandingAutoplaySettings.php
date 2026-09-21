<?php

namespace App\Modules\Music\Filament\Pages;

use App\Models\Setting;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Support\LandingAutoplayTrackResolver;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Admin control for the Music landing page's autoplay enhancement — picks an
 * ordered list of existing Tracks (or none, to disable autoplay entirely),
 * which the landing page plays one after another in that order. Same
 * `settings`-table pattern via SettingsRepository as
 * App\Modules\Commerce\Filament\Pages\DownloadAccessSettings, its own group
 * ("music") rather than a new standalone config mechanism or a new column
 * on any Music table. Never touches Featured Album/Single selection
 * (Album::is_featured/Single::is_featured) — a completely separate concern.
 *
 * The Selects only ever write Track `id`s (stored as a JSON list of integers
 * under landing_autoplay_track_ids) — never a URL, media path, or filename —
 * and only ever offer currently Published tracks, so an admin cannot pick a
 * track that is already unreachable. See App\Modules\Music\Support\
 * LandingAutoplayTrackResolver for the public-side fail-safe (a track later
 * unpublished/deleted is simply skipped, not an error).
 */
class MusicLandingAutoplaySettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Music';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'music/landing-autoplay';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSpeakerWave;

    protected static ?string $navigationLabel = 'Landing Page Autoplay';

    protected static ?string $title = 'Landing Page Autoplay';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, string>|null
     */
    private ?array $trackOptions = null;

    /**
     * Hidden from navigation while the feature's master switch
     * (config('features.music_landing_autoplay_enabled'), env
     * MUSIC_LANDING_AUTOPLAY_ENABLED) is off — same pattern
     * SubscriptionResource::shouldRegisterNavigation() already uses for
     * member_subscription_enabled. The configured Track (if any) is left
     * untouched in the settings table either way; this only hides the admin
     * surface, matching this app's one existing feature-flagged-navigation
     * convention (there is no finer-grained per-page access check anywhere
     * else in the admin to follow instead).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('features.music_landing_autoplay_enabled');
    }

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
            Section::make('Autoplay Tracks')
                ->description(
                    config('features.music_landing_autoplay_enabled')
                        ? 'When set, the Music landing page attempts to automatically play these tracks in full, one after another in the order below, for every visitor (guest or registered), subject only to the browser\'s own autoplay policy — these tracks are exempt from the guest preview cutoff and the daily-listen quota that apply everywhere else on the site. Remove every row to disable landing page autoplay entirely.'
                        : 'Landing page autoplay is currently switched off for the whole site (MUSIC_LANDING_AUTOPLAY_ENABLED). You can still configure tracks below — they will start autoplaying as soon as that switch is turned back on.'
                )
                ->schema([
                    Repeater::make('tracks')
                        ->label('Playlist')
                        ->schema([
                            Select::make('track_id')
                                ->label('Track')
                                ->options(fn (): array => $this->trackOptions())
                                ->searchable()
                                ->native(false)
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        ])
                        ->reorderable()
                        ->addActionLabel('Add track')
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $this->trackOptions()[$state['track_id'] ?? null] ?? null)
                        ->helperText('Tracks play top to bottom and then repeat from the first until the visitor clicks Stop Music. Drag to reorder. Only published tracks are listed; a track that is later unpublished or deleted is simply skipped — nothing breaks.'),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function trackOptions(): array
    {
        return $this->trackOptions ??= Track::query()
            ->published()
            ->with(['album', 'single'])
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (Track $track): array => [
                $track->id => "{$track->title} — ".($track->album->title ?? $track->single->title ?? 'Unknown release'),
            ])
            ->all();
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

        $ids = collect($state['tracks'] ?? [])
            ->pluck('track_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        // An empty list is stored as '[]' (not null), so clearing it really
        // does disable autoplay instead of falling back to the legacy
        // single-track setting (see LandingAutoplayTrackResolver).
        $settings->set('music', 'landing_autoplay_track_ids', json_encode($ids));

        $this->recordAudit();

        Notification::make()->title('Landing Page Autoplay settings saved')->success()->send();

        $this->form->fill($this->loadFormState());
    }

    private function recordAudit(): void
    {
        $entity = Setting::query()->where('group', 'music')->where('key', 'landing_autoplay_track_ids')->first();

        if (! $entity) {
            return;
        }

        app(AuditLogService::class)->record(Auth::user(), 'settings.updated', $entity, [
            'group' => 'music',
            'keys' => ['landing_autoplay_track_ids'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormState(): array
    {
        $ids = app(LandingAutoplayTrackResolver::class)->configuredIds(app(SettingsRepository::class));

        return [
            'tracks' => array_map(fn (int $id): array => ['track_id' => $id], $ids),
        ];
    }
}
