<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProses;

use App\Models\User;
use App\Modules\PoetryProse\Actions\RestorePoetryProseRevisionAction;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages\CreatePoetryProse;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages\EditPoetryProse;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages\ListPoetryProses;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Schemas\PoetryProseForm;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Tables\PoetryProsesTable;
use App\Modules\PoetryProse\Models\PoetryProse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PoetryProseResource extends Resource
{
    protected static ?string $model = PoetryProse::class;

    protected static string|UnitEnum|null $navigationGroup = 'Poetry/Prose';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Entries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PoetryProseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PoetryProsesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPoetryProses::route('/'),
            'create' => CreatePoetryProse::route('/create'),
            'edit' => EditPoetryProse::route('/{record}/edit'),
        ];
    }

    /**
     * Mirrors PageResource::restoreRevisionAction() exactly — a header
     * action on the Edit page, not a relation manager: restoring writes
     * straight to the database (see RestorePoetryProseRevisionAction), so
     * the caller redirects back to the same edit page afterward to force
     * every field to refill from the now-restored row.
     */
    public static function restoreRevisionAction(): Action
    {
        return Action::make('restoreRevision')
            ->label('Restore a Revision')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->schema([
                Select::make('revision_id')
                    ->label('Version')
                    ->options(fn (PoetryProse $record): array => $record->revisions()
                        ->latest('version')
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn ($revision) => [$revision->id => "v{$revision->version} — {$revision->created_at?->toDayDateTimeString()}"])
                        ->all())
                    ->required()
                    ->searchable(),
            ])
            ->requiresConfirmation()
            ->modalDescription('Restore this version? The current content is saved as a new revision first, so nothing is lost.')
            ->action(function (PoetryProse $record, array $data) {
                /** @var User $actor */
                $actor = Auth::user();

                $revision = $record->revisions()->findOrFail($data['revision_id']);

                app(RestorePoetryProseRevisionAction::class)->handle($record, $revision, $actor);

                Notification::make()->title('Revision restored')->success()->send();

                return redirect(static::getUrl('edit', ['record' => $record]));
            });
    }
}
