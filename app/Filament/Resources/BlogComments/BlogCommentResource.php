<?php

namespace App\Filament\Resources\BlogComments;

use App\Actions\Blog\ModerateBlogCommentAction;
use App\Enums\BlogCommentStatus;
use App\Filament\Resources\BlogComments\Pages\ListBlogComments;
use App\Models\BlogComment;
use App\Models\BlogCommentReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Blog → Comments: every member comment, with a red flag on the ones
 * members have reported. Verify keeps a reported comment and clears its
 * reports; Revoke hides it from the site; Restore brings a revoked one
 * back. See ModerateBlogCommentAction.
 */
class BlogCommentResource extends Resource
{
    protected static ?string $model = BlogComment::class;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?string $navigationLabel = 'Comments';

    protected static ?string $modelLabel = 'comment';

    protected static ?string $slug = 'blog/comments';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $reported = self::reportedQuery(BlogComment::query())->count();

        return $reported > 0 ? (string) $reported : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Reported comments awaiting review';
    }

    /**
     * Published comments with at least one unresolved report.
     *
     * @param  Builder<BlogComment>  $query
     * @return Builder<BlogComment>
     */
    public static function reportedQuery(Builder $query): Builder
    {
        return $query->where('status', BlogCommentStatus::Published)->whereHas('openReports');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Comment')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Member'),
                        TextEntry::make('post.title')->label('Post'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('body')->label('Text')->columnSpanFull(),
                        TextEntry::make('reviewedBy.name')->label('Last reviewed by')->placeholder('—'),
                        TextEntry::make('reviewed_at')->label('Reviewed')->dateTime()->placeholder('—'),
                    ]),
                self::visitorSection(),
                Section::make('Reports')
                    ->visible(fn (BlogComment $record): bool => $record->reports()->exists())
                    ->schema([
                        RepeatableEntry::make('reports')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('user.name')->label('Reported by'),
                                TextEntry::make('reason')->badge()->color('danger'),
                                TextEntry::make('created_at')->label('When')->since(),
                                TextEntry::make('resolved_at')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Resolved' : 'Open')
                                    ->placeholder('Open')
                                    ->badge()
                                    ->color(fn (BlogCommentReport $record): string => $record->resolved_at ? 'gray' : 'danger'),
                                TextEntry::make('details')->label('Details')->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Where the member posted from — IP, cached geolocation (IpGeolocator)
     * and browser. Shared with the Reactions resource.
     */
    public static function visitorSection(): Section
    {
        return Section::make('Posted from')
            ->columns(3)
            ->collapsible()
            ->schema([
                TextEntry::make('ip_address')->label('IP address')->copyable()->placeholder('—'),
                TextEntry::make('ipLocation.country')
                    ->label('Country')
                    ->formatStateUsing(fn ($state, $record): string => trim(($record->ipLocation?->flag() ?? '').' '.$state))
                    ->placeholder('—'),
                TextEntry::make('ipLocation.region')->label('Region / state')->placeholder('—'),
                TextEntry::make('ipLocation.city')->label('City')->placeholder('—'),
                TextEntry::make('ipLocation.postal')->label('Postal code')->placeholder('—'),
                TextEntry::make('ipLocation.timezone')->label('Timezone')->placeholder('—'),
                TextEntry::make('ipLocation.network')->label('Network / ISP')->placeholder('—')->columnSpan(2),
                TextEntry::make('ipLocation.latitude')
                    ->label('Map')
                    ->formatStateUsing(fn ($state, $record): string => 'Open approximate location')
                    ->url(fn ($record): ?string => $record->ipLocation?->mapUrl(), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('—'),
                TextEntry::make('location_status')
                    ->label('Lookup')
                    ->state(fn ($record): string => match (true) {
                        $record->ipLocation === null => 'Pending',
                        $record->ipLocation->is_private => 'Private / local network (not looked up)',
                        filled($record->ipLocation->error) => 'Failed: '.$record->ipLocation->error,
                        default => 'Looked up '.$record->ipLocation->looked_up_at?->diffForHumans(),
                    }),
                TextEntry::make('user_agent')->label('Browser')->placeholder('—')->columnSpan(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'post', 'ipLocation'])->withCount('openReports'))
            ->columns([
                IconColumn::make('open_reports_count')
                    ->label('')
                    ->icon(fn (int $state): ?Heroicon => $state > 0 ? Heroicon::Flag : null)
                    ->color('danger')
                    ->tooltip(fn (int $state): ?string => $state > 0 ? "Reported by {$state} member".($state === 1 ? '' : 's') : null),
                TextColumn::make('body')
                    ->label('Comment')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Member')
                    ->searchable(),
                TextColumn::make('post.title')
                    ->label('Post')
                    ->limit(40)
                    ->url(fn (BlogComment $record): ?string => $record->post?->isLive() ? $record->post->url().'#comment-'.$record->id : null, shouldOpenInNewTab: true),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('ip_address')
                    ->label('IP / location')
                    ->searchable()
                    ->description(fn (BlogComment $record): string => $record->ipLocation?->summary() ?? 'Looking up…')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('open_reports_count')
                    ->label('Reports')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BlogCommentStatus::class),
                SelectFilter::make('blog_post_id')->label('Post')->relationship('post', 'title')->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('verify')
                    ->label('Verify')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (BlogComment $record): bool => $record->isVisible() && $record->open_reports_count > 0)
                    ->requiresConfirmation()
                    ->modalHeading('Verify this comment?')
                    ->modalDescription('The comment stays published and its reports are cleared.')
                    ->action(function (BlogComment $record): void {
                        app(ModerateBlogCommentAction::class)->verify($record, Auth::user());
                        Notification::make()->title('Comment verified')->success()->send();
                    }),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (BlogComment $record): bool => $record->isVisible())
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this comment?')
                    ->modalDescription('The comment is hidden from the website (you can restore it later) and its reports are cleared.')
                    ->action(function (BlogComment $record): void {
                        app(ModerateBlogCommentAction::class)->revoke($record, Auth::user());
                        Notification::make()->title('Comment revoked')->success()->send();
                    }),
                Action::make('restore')
                    ->label('Restore')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (BlogComment $record): bool => ! $record->isVisible())
                    ->requiresConfirmation()
                    ->action(function (BlogComment $record): void {
                        app(ModerateBlogCommentAction::class)->restore($record, Auth::user());
                        Notification::make()->title('Comment restored')->success()->send();
                    }),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogComments::route('/'),
        ];
    }
}
