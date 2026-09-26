<?php

namespace App\Models;

use App\Enums\BlogCommentStatus;
use App\Enums\BlogPostStatus;
use App\Shared\Support\Blog\BlogContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A blog post (Admin → Blog → Posts, public at /blog/{slug}). Live on the
 * site only while Published with a published_at in the past — see
 * scopeLive(). reading_minutes is derived from the body on every save.
 */
#[Fillable([
    'title', 'slug', 'excerpt', 'body', 'cover_media_id', 'blog_category_id', 'author_id',
    'status', 'published_at', 'is_featured', 'allow_comments', 'created_by', 'updated_by',
])]
class BlogPost extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post): void {
            $post->reading_minutes = BlogContent::readingMinutes($post->body);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Published and due: what the public site may show.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', BlogPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isLive(): bool
    {
        return $this->status === BlogPostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function url(): string
    {
        return route('blog.show', $this);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag')->orderBy('name');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function visibleComments(): HasMany
    {
        return $this->comments()->where('status', BlogCommentStatus::Published);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(BlogReactionRecord::class);
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
