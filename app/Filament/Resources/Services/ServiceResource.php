<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Services\Schemas\ServiceForm;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Services → Services. Public at /services/{slug} while published; the
 * /services landing page lists every published service, and those marked
 * "Show on homepage" appear in the homepage Services section. SEO is saved
 * to the shared seo_metadata relation by the Create/Edit pages.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|UnitEnum|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $slug = 'services';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $touch = fn (Service $record) => $record->forceFill(['updated_by' => Auth::id()])->saveQuietly();

        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Service $record): ?string => $record->tagline ?: null),
                TextColumn::make('slug')
                    ->prefix('/services/')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('faqs')
                    ->label('FAQs')
                    ->state(fn (Service $record): int => count($record->faqs ?? []))
                    ->toggleable(),
                ToggleColumn::make('is_featured')
                    ->label('Homepage')
                    ->afterStateUpdated($touch),
                ToggleColumn::make('is_published')
                    ->label('Published')
                    ->afterStateUpdated($touch),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
                TernaryFilter::make('is_featured')->label('On homepage'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Service $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Service $record): bool => $record->is_published),
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
