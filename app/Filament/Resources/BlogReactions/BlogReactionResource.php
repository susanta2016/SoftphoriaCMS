<?php

namespace App\Filament\Resources\BlogReactions;

use App\Enums\BlogReaction;
use App\Filament\Resources\BlogComments\BlogCommentResource;
use App\Filament\Resources\BlogReactions\Pages\ListBlogReactions;
use App\Models\BlogReactionRecord;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Blog → Reactions: a read-only log of members' emoji reactions, with the
 * IP address and approximate location each came from (IpGeolocator).
 */
class BlogReactionResource extends Resource
{
    protected static ?string $model = BlogReactionRecord::class;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?string $navigationLabel = 'Reactions';

    protected static ?string $modelLabel = 'reaction';

    protected static ?string $slug = 'blog/reactions';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Reaction')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reaction')
                            ->formatStateUsing(fn (BlogReaction $state): string => $state->emoji().' '.$state->getLabel()),
                        TextEntry::make('user.name')->label('Member'),
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('post.title')->label('Post')->columnSpanFull(),
                    ]),
                BlogCommentResource::visitorSection()->heading('Reacted from'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'post', 'ipLocation']))
            ->columns([
                TextColumn::make('reaction')
                    ->formatStateUsing(fn (BlogReaction $state): string => $state->emoji().' '.$state->getLabel()),
                TextColumn::make('user.name')
                    ->label('Member')
                    ->searchable(),
                TextColumn::make('post.title')
                    ->label('Post')
                    ->limit(45)
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP / location')
                    ->searchable()
                    ->copyable()
                    ->description(fn (BlogReactionRecord $record): string => $record->ip_address ? ($record->ipLocation?->summary() ?? 'Looking up…') : '—')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('reaction')->options(BlogReaction::class),
                SelectFilter::make('blog_post_id')->label('Post')->relationship('post', 'title')->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogReactions::route('/'),
        ];
    }
}
