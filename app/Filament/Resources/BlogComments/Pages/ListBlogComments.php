<?php

namespace App\Filament\Resources\BlogComments\Pages;

use App\Enums\BlogCommentStatus;
use App\Filament\Resources\BlogComments\BlogCommentResource;
use App\Models\BlogComment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListBlogComments extends ListRecords
{
    protected static string $resource = BlogCommentResource::class;

    public function getTabs(): array
    {
        $reported = BlogCommentResource::reportedQuery(BlogComment::query())->count();

        return [
            'all' => Tab::make('All'),
            'reported' => Tab::make('Reported')
                ->icon(Heroicon::Flag)
                ->badge($reported ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => BlogCommentResource::reportedQuery($query)),
            'revoked' => Tab::make('Revoked')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BlogCommentStatus::Revoked)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return BlogCommentResource::reportedQuery(BlogComment::query())->exists() ? 'reported' : 'all';
    }
}
