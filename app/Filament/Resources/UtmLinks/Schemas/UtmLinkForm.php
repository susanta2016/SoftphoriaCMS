<?php

namespace App\Filament\Resources\UtmLinks\Schemas;

use App\Shared\Support\Marketing\UtmDestination;
use App\Shared\Support\Marketing\UtmParameters;
use App\Shared\Support\Marketing\UtmUrlGenerator;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Route;

class UtmLinkForm
{
    public const string CUSTOM_CHANNEL = '__custom';

    private const string VALUE_MESSAGE = 'Use letters, numbers, spaces, dots, dashes or underscores only.';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Link')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('For your reference only, e.g. "Instagram bio — summer launch".'),

                        Select::make('channel')
                            ->label('Channel')
                            ->options(self::channelOptions())
                            ->required()
                            ->live()
                            ->afterStateHydrated(function (Select $component, Get $get): void {
                                $source = $get('utm_source');

                                if (filled($source)) {
                                    $component->state(array_key_exists($source, config('utm.channels', [])) ? $source : self::CUSTOM_CHANNEL);
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $medium = config("utm.channels.{$state}.medium");

                                if ($medium !== null) {
                                    $set('utm_medium', $medium);
                                }
                            }),

                        TextInput::make('utm_source')
                            ->label('Custom source')
                            ->helperText('Sent as utm_source, e.g. youtube.')
                            ->visible(fn (Get $get): bool => $get('channel') === self::CUSTOM_CHANNEL)
                            ->required(fn (Get $get): bool => $get('channel') === self::CUSTOM_CHANNEL)
                            ->maxLength(UtmParameters::MAX_LENGTH)
                            ->regex(UtmParameters::ADMIN_VALUE_REGEX)
                            ->validationMessages(['regex' => self::VALUE_MESSAGE])
                            ->live(onBlur: true),

                        self::valueInput('utm_medium', 'Medium', required: true)
                            ->helperText('Filled in from the channel; change it if needed.'),

                        self::valueInput('utm_campaign', 'Campaign', required: true)
                            ->helperText('E.g. summer_launch.'),

                        self::valueInput('utm_content', 'Content (optional)')
                            ->helperText('Tells apart links in the same campaign, e.g. bio or story.'),

                        self::valueInput('utm_term', 'Term (optional)'),

                        TextInput::make('destination')
                            ->label('Destination')
                            ->required()
                            ->placeholder('/music')
                            ->datalist(self::destinationSuggestions())
                            ->maxLength(UtmDestination::MAX_LENGTH)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->helperText('Any public page on this site. Pick a section from the suggestions, or paste the address of a specific page (a track, episode, resource, Light Post…) copied from the website.')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (! UtmDestination::isValid(is_string($value) ? $value : null)) {
                                    $fail("Enter a page on this website: a path like /register, or an http(s) address on this site's own domain.");
                                }
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => UtmDestination::toRelative($state) ?? $state),

                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Turn off when the link is no longer in use.'),
                    ]),

                Section::make('Generated link')
                    ->schema([
                        Placeholder::make('generated_url')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string => self::previewUrl($get)),
                    ]),
            ]);
    }

    /**
     * Turns the form's Channel choice into the stored utm_source and drops
     * the non-column `channel` key — used by the Create/Edit pages.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $channel = $data['channel'] ?? null;

        if ($channel !== null && $channel !== self::CUSTOM_CHANNEL) {
            $data['utm_source'] = $channel;
        }

        unset($data['channel']);

        foreach (['utm_content', 'utm_term'] as $optional) {
            if (blank($data[$optional] ?? null)) {
                $data[$optional] = null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private static function channelOptions(): array
    {
        $options = [];

        foreach (config('utm.channels', []) as $value => $channel) {
            $options[$value] = $channel['label'];
        }

        return [...$options, self::CUSTOM_CHANNEL => 'Custom…'];
    }

    /**
     * Site-relative paths of the main public sections, offered as Destination
     * suggestions (free text is still accepted for any other own-site page).
     *
     * @return array<int, string>
     */
    private static function destinationSuggestions(): array
    {
        $routes = [
            'home', 'register.show', 'music.index', 'podcast.index', 'podcast.episodes.index',
            'inspirational-resources.index', 'inspirational-resources.gratitude-journal', 'poetry-prose.index', 'contact.index',
        ];

        $paths = [];

        foreach ($routes as $name) {
            if (Route::has($name)) {
                $paths[] = route($name, absolute: false);
            }
        }

        $paths[] = '/about';

        return array_values(array_unique($paths));
    }

    private static function valueInput(string $name, string $label, bool $required = false): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required($required)
            ->maxLength(UtmParameters::MAX_LENGTH)
            ->regex(UtmParameters::ADMIN_VALUE_REGEX)
            ->validationMessages(['regex' => self::VALUE_MESSAGE])
            ->live(onBlur: true);
    }

    private static function previewUrl(Get $get): string
    {
        $channel = $get('channel');
        $source = $channel === self::CUSTOM_CHANNEL ? $get('utm_source') : $channel;
        $destination = $get('destination');

        if (blank($source) || blank($get('utm_campaign')) || ! UtmDestination::isValid(is_string($destination) ? $destination : null)) {
            return 'Choose a channel, enter a campaign and a valid destination to see the link.';
        }

        return UtmUrlGenerator::generate($destination, [
            'utm_source' => $source,
            'utm_medium' => $get('utm_medium'),
            'utm_campaign' => $get('utm_campaign'),
            'utm_content' => $get('utm_content'),
            'utm_term' => $get('utm_term'),
        ]);
    }
}
