<?php

namespace App\Filament\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Shared\Support\Blog\BlogSettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
 * Blog → Blog Settings: the /blog landing page's copy and SEO, layout and
 * card style, which extras show on a post, and the lead-capture call to
 * action at the end of every article. Stored in the `settings` table
 * (group "blog") through BlogSettingsRepository.
 */
class BlogSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'blog/settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Blog Settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(BlogSettingsRepository::class)->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blog landing page')
                ->description('The /blog page header and how it appears in search results.')
                ->schema([
                    TextInput::make('title')->label('Heading')->required()->maxLength(120),
                    Textarea::make('intro')->label('Intro text')->rows(2)->maxLength(300),
                    SeoFields::metaTitle('meta_title')
                        ->helperText('Optional. Defaults to "Heading — Site name".'),
                    SeoFields::metaDescription('meta_description')
                        ->helperText('Optional. Defaults to the intro text.'),
                ]),

            Section::make('Layout & cards')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('layout')->options(BlogSettingsRepository::LAYOUTS)->required()->native(false),
                        Select::make('card_style')->label('Card style')->options(BlogSettingsRepository::CARD_STYLES)->required()->native(false),
                        TextInput::make('per_page')->label('Posts per page')->numeric()->minValue(3)->maxValue(30)->required(),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('show_featured')->label('Highlight the featured post at the top of /blog'),
                        Toggle::make('show_author')->label('Show the author'),
                        Toggle::make('show_reading_time')->label('Show reading time'),
                        Toggle::make('show_toc')->label('Table of contents on posts'),
                        Toggle::make('show_share')->label('Share buttons on posts'),
                        Toggle::make('show_related')->label('Related posts under each post'),
                        Toggle::make('show_newsletter')->label('Newsletter signup box (needs Newsletter Signup switched on)'),
                    ]),
                ]),

            Section::make('Lead capture — end of every article')
                ->description('A call-to-action card shown after each post. Leave the heading empty to hide it.')
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
        $repository = app(BlogSettingsRepository::class);
        $repository->save($this->form->getState());

        Notification::make()->title('Blog settings saved')->success()->send();

        $this->form->fill($repository->all());
    }
}
