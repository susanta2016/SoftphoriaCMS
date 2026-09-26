<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An old slug of a tool that has been public, 301-redirected to the tool's
 * current URL. Written by App\Tools\ToolPublisher::recordSlugChange().
 */
#[Fillable(['old_slug', 'tool_id'])]
class ToolRedirect extends Model
{
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
