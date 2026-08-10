<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value', 'type'])]
class Setting extends Model
{
    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }
}
