<?php

namespace App\Filament\Resources\BlogReactions\Pages;

use App\Filament\Resources\BlogReactions\BlogReactionResource;
use Filament\Resources\Pages\ListRecords;

class ListBlogReactions extends ListRecords
{
    protected static string $resource = BlogReactionResource::class;
}
