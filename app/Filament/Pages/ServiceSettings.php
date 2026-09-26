<?php

namespace App\Filament\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Shared\Support\Services\ServiceSettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Services → Services Settings: the /services landing page copy and SEO,
 * and the call to action shown on the landing page and every service page.
 * Stored in the `settings` table (group "services").
 */
class ServiceSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'services-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Services Settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(ServiceSettingsRepository::class)->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Services landing page')
                ->description('The /services page header and how it appears in search results.')
                ->schema([
                    TextInput::make('title')->label('Heading')->required()->maxLength(120),
                    Textarea::make('intro')->label('Intro text')->rows(2)->maxLength(300),
                    SeoFields::metaTitle('meta_title')->helperText('Optional. Defaults to "Services — Site name".'),
                    SeoFields::metaDescription('meta_description')->helperText('Optional. Defaults to the intro text.'),
                ]),

            Section::make('Call to action')
                ->description('Shown on the landing page and at the end of every service page. Leave the heading empty to hide it.')
                ->schema([
                    TextInput::make('cta_heading')->label('Heading')->maxLength(120),
                    Textarea::make('cta_text')->label('Text')->rows(2)->maxLength(300),
                    Grid::make(2)->schema([
                        TextInput::make('cta_label')->label('Button label')->maxLength(40),
                        TextInput::make('cta_url')->label('Button URL')->maxLength(255)
                            ->rule('regex:/^(https?:\/\/|\/|#)/i')
                            ->validationMessages(['regex' => 'Use a full URL (https://…) or a path starting with /.']),
                    ]),
                ]),

            Section::make('Service pages')
                ->schema([
                    Toggle::make('show_related_posts')
                        ->label('Show related blog posts')
                        ->helperText('Live posts tagged with one of the service\'s technologies (needs Blog Posts switched on).'),
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
        $repository = app(ServiceSettingsRepository::class);
        $repository->save($this->form->getState());

        Notification::make()->title('Services settings saved')->success()->send();

        $this->form->fill($repository->all());
    }
}
