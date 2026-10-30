<?php

namespace App\Modules\Newsletter\Filament\Resources\Templates;

use App\Modules\Newsletter\Enums\TemplateStatus;
use App\Modules\Newsletter\Filament\Concerns\GatedByNewsletterFeature;
use App\Modules\Newsletter\Filament\Resources\Templates\Pages\CreateNewsletterTemplate;
use App\Modules\Newsletter\Filament\Resources\Templates\Pages\EditNewsletterTemplate;
use App\Modules\Newsletter\Filament\Resources\Templates\Pages\ListNewsletterTemplates;
use App\Modules\Newsletter\Filament\Support\NewsletterPreviewActions;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\StarterTemplate;
use App\Modules\Newsletter\Support\TemplateVariables;
use App\Shared\Services\AuditLogService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Newsletter Marketing > Email Templates: reusable HTML/CSS layouts.
 * Campaigns send from their own snapshot, so editing or archiving a
 * template never changes a campaign that is already scheduled or sent.
 */
class NewsletterTemplateResource extends Resource
{
    use GatedByNewsletterFeature;

    protected static ?string $model = NewsletterTemplate::class;

    protected static string|UnitEnum|null $navigationGroup = 'Newsletter Marketing';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'newsletter/templates';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static ?string $modelLabel = 'email template';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Template')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(150)->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(TemplateStatus::class)
                            ->default(TemplateStatus::Active->value)
                            ->required()
                            ->helperText('Only Active templates can be chosen for a campaign.'),
                        Textarea::make('description')->rows(2)->maxLength(1000)->columnSpanFull(),
                        TextInput::make('subject')
                            ->label('Default subject')
                            ->maxLength(200)
                            ->rules(['not_regex:/[\r\n]/', self::knownPlaceholders()])
                            ->helperText('Pre-filled when a campaign uses this template.'),
                        TextInput::make('preheader')
                            ->label('Default preheader')
                            ->maxLength(255)
                            ->helperText('The preview text shown after the subject in the inbox.'),
                    ]),

                Section::make('HTML')
                    ->description('Email-safe HTML: tables and inline styles work everywhere; scripts, forms and external stylesheets are removed when sending.')
                    ->schema([
                        CodeEditor::make('html_content')
                            ->hiddenLabel()
                            ->language(Language::Html)
                            ->default(StarterTemplate::html())
                            ->required()
                            ->rules([self::knownPlaceholders(), self::requiresUnsubscribe()])
                            ->helperText("Placeholders you can use:\n".TemplateVariables::reference()),
                    ]),

                Section::make('CSS')
                    ->description('Inlined into the HTML when the campaign is prepared; @media rules stay in a <style> block for mobile layouts.')
                    ->collapsed()
                    ->schema([
                        CodeEditor::make('css_content')
                            ->hiddenLabel()
                            ->language(Language::Css)
                            ->default(StarterTemplate::css()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('campaigns'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('subject')->label('Default subject')->limit(50)->placeholder('—')->toggleable(),
                TextColumn::make('campaigns_count')->label('Campaigns')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(TemplateStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    NewsletterPreviewActions::preview(),
                    NewsletterPreviewActions::sendTest(),
                    self::duplicateAction(),
                    self::deleteAction(DeleteAction::make()),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('No templates yet')
            ->emptyStateDescription('Create a template once — e.g. "Monthly Newsletter" — and reuse it for every campaign.');
    }

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->action(function (NewsletterTemplate $record): void {
                $name = Str::limit('Copy of '.$record->name, 140, '');
                $suffix = 1;

                while (NewsletterTemplate::query()->where('name', $name)->exists()) {
                    $name = Str::limit('Copy of '.$record->name, 140, '').' ('.++$suffix.')';
                }

                $copy = $record->replicate(['campaigns_count']);
                $copy->forceFill([
                    'name' => $name,
                    'status' => TemplateStatus::Draft,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ])->save();

                app(AuditLogService::class)->record(Auth::user(), 'newsletter_template.duplicated', $copy, ['source_template_id' => $record->id]);

                Notification::make()->title("Created \"{$copy->name}\" as a draft.")->success()->send();
            });
    }

    /**
     * A template a campaign points at is archived, not deleted, so campaign
     * history keeps showing which layout was used.
     */
    public static function deleteAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->before(function (NewsletterTemplate $record, DeleteAction $action): void {
                if ($record->campaigns()->exists()) {
                    Notification::make()
                        ->title('This template is used by campaigns.')
                        ->body('Set its status to Archived instead — that hides it from new campaigns.')
                        ->danger()
                        ->send();

                    $action->cancel();
                }
            })
            ->after(fn (NewsletterTemplate $record) => app(AuditLogService::class)->record(Auth::user(), 'newsletter_template.deleted', $record, ['name' => $record->name]));
    }

    /**
     * Field rule. Wrapped in an outer closure because Filament evaluates a
     * bare closure in rules() with its own dependency injection.
     */
    public static function knownPlaceholders(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (($unknown = TemplateVariables::unknownIn((string) $value)) !== []) {
                $fail('Unknown placeholder(s): '.implode(', ', array_map(fn (string $name): string => "{{ {$name} }}", $unknown)).'.');
            }
        };
    }

    private static function requiresUnsubscribe(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (! TemplateVariables::hasUnsubscribeLink((string) $value)) {
                $fail('The template must include {{ unsubscribe_url }} or {{ footer }} — every marketing email needs a working unsubscribe link.');
            }
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterTemplates::route('/'),
            'create' => CreateNewsletterTemplate::route('/create'),
            'edit' => EditNewsletterTemplate::route('/{record}/edit'),
        ];
    }
}
