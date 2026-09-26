<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A blog tag (Admin → Blog → Tags). Tag archives (/blog/tag/{slug}) are
 * rendered noindex,follow: they mostly repeat category/post content, so
 * they help visitors browse without competing with it in search.
 */
#[Fillable(['name', 'slug'])]
class BlogTag extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function url(): string
    {
        return route('blog.tag', $this);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }
}
