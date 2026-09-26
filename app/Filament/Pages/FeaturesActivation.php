<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Shared\Services\AuditLogService;
use App\Shared\Support\Features\Features;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Website Setup → Features Activation: one switch per frontend module /
 * feature registered in config/features.php, grouped (Blog System,
 * Website…), each with its description, its "Requires: …" dependencies and
 * an Edit button to the admin screen that manages it. Saving writes the
 * `features` settings group read by App\Shared\Support\Features\Features.
 */
class FeaturesActivation extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Website Setup';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'website-setup/features';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $title = 'Features Activation';

    protected ?string $subheading = 'Turn frontend modules and their individual features on or off. Switching something off hides it from the public website only — its admin screens stay available.';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadState());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $features = app(Features::class);

        return $schema->components(collect($features->groups())
            ->map(fn (array $group): Section => Section::make($group['label'])
                ->schema(collect($group['features'])
                    ->map(fn (array $feature, string $key): Component => $this->featureRow($key, $feature, $features))
                    ->values()
                    ->all()))
            ->values()
            ->all());
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
        $features = app(Features::class);
        $state = $this->form->getState();
        $changed = [];

        foreach ($this->toggleableKeys($features) as $key) {
            $on = (bool) ($state[self::stateKey($key)] ?? false);

            if ($features->switchedOn($key) !== $on) {
                $changed[$key] = $on;
            }

            $features->set($key, $on);
        }

        if ($changed !== [] && ($entity = Setting::query()->where('group', Features::SETTINGS_GROUP)->first())) {
            app(AuditLogService::class)->record(Auth::user(), 'features.updated', $entity, ['changed' => $changed]);
        }

        Notification::make()->title('Features saved')->success()->send();

        $this->form->fill($this->loadState());
    }

    /**
     * Filament state paths are dot-separated, so a key like "blog.posts"
     * is stored under "blog__posts" in the form.
     */
    public static function stateKey(string $featureKey): string
    {
        return str_replace('.', '__', $featureKey);
    }

    /**
     * @param  array<string, mixed>  $feature
     */
    private function featureRow(string $key, array $feature, Features $features): Component
    {
        $requires = collect($feature['requires'] ?? [])
            ->map(fn (string $required): string => $features->definition($required)['label'])
            ->implode(', ');

        $toggleable = ($feature['toggleable'] ?? true) !== false;

        $control = $toggleable
            ? Toggle::make(self::stateKey($key))
                ->label($feature['label'])
                ->helperText($feature['description'])
                ->hint($requires !== '' ? "Requires: {$requires}" : null)
                ->hintIcon($requires !== '' ? Heroicon::OutlinedLink : null)
                ->hintColor('warning')
                ->live()
                // A switch whose requirement is off can still be set, but
                // shows that it won't take effect until that's on too.
                ->belowContent(fn (Get $get): ?string => $this->unmetRequirement($feature, $get, $features))
            : Text::make(fn (): string => "{$feature['label']} — {$feature['description']}")
                ->key("{$key}_text");

        $edit = isset($feature['edit'])
            ? Actions::make([
                Action::make(self::stateKey($key).'_edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (): string => $feature['edit']::getUrl()),
            ])->alignEnd()->key("{$key}_actions")
            : Text::make('')->key("{$key}_spacer");

        return Grid::make(['default' => 1, 'md' => 6])
            ->schema([
                $control->columnSpan(['default' => 1, 'md' => 5]),
                $edit->columnSpan(['default' => 1, 'md' => 1]),
            ])
            ->key("{$key}_row");
    }

    /**
     * @param  array<string, mixed>  $feature
     */
    private function unmetRequirement(array $feature, Get $get, Features $features): ?string
    {
        foreach ($feature['requires'] ?? [] as $required) {
            $definition = $features->definition($required);

            if (($definition['toggleable'] ?? true) !== false && ! $get(self::stateKey($required))) {
                return "Inactive on the website while {$definition['label']} is off.";
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function toggleableKeys(Features $features): array
    {
        return collect($features->groups())
            ->flatMap(fn (array $group): array => $group['features'])
            ->filter(fn (array $feature): bool => ($feature['toggleable'] ?? true) !== false)
            ->keys()
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function loadState(): array
    {
        $features = app(Features::class);

        return collect($this->toggleableKeys($features))
            ->mapWithKeys(fn (string $key): array => [self::stateKey($key) => $features->switchedOn($key)])
            ->all();
    }
}
