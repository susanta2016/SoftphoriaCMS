<?php

namespace App\Filament\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Tools\ToolSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Tools → Tools Settings: the /tools hub copy and SEO, and the default call
 * to action for tools that don't set their own. Stored in the `settings`
 * table (group "tools").
 */
class ToolsSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'tools-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Tools Settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(ToolSettings::class)->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tools hub')
                ->description('The /tools page header and how it appears in search results.')
                ->schema([
                    TextInput::make('title')->label('Heading')->required()->maxLength(120),
                    Textarea::make('intro')->label('Intro text')->rows(2)->maxLength(300),
                    SeoFields::metaTitle('meta_title')->helperText('Optional. Defaults to "Free Tools — Site name".'),
                    SeoFields::metaDescription('meta_description')->helperText('Optional. Defaults to the intro text.'),
                ]),

            Section::make('Default call to action')
                ->description('Shown at the end of every tool page that has no call to action of its own. Leave the heading empty to hide it.')
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
        $settings = app(ToolSettings::class);
        $settings->save($this->form->getState());

        Notification::make()->title('Tools settings saved')->success()->send();

        $this->form->fill($settings->all());
    }
}
