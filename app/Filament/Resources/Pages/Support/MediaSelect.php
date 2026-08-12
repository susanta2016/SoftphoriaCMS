<?php

namespace App\Filament\Resources\Pages\Support;

use App\Models\Media;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;

/**
 * Searchable picker with thumbnails over existing Media Library image rows
 * (ADMIN-006's "reuse the existing Media Library for featured and inline
 * images" decision, "use a searchable media picker with thumbnails") —
 * never a new upload path. Reused for the page's featured image, the SEO
 * card's OG/Twitter images, and any section type that references an image.
 *
 * Search is server-side (getSearchResultsUsing) rather than a static
 * ->options() list, so this scales past however many images the Media
 * Library holds instead of loading them all up front.
 */
class MediaSelect
{
    public static function make(string $name, string $label, bool $multiple = false): Select
    {
        $select = Select::make($name)
            ->label($label)
            ->searchable()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search): array => static::results($search));

        if ($multiple) {
            return $select
                ->multiple()
                ->getOptionLabelsUsing(fn (array $values): array => Media::query()
                    ->whereIn('id', $values)
                    ->get()
                    ->mapWithKeys(fn (Media $media): array => [$media->id => static::label($media)])
                    ->all());
        }

        return $select->getOptionLabelUsing(fn (mixed $value): ?string => static::label(
            Media::query()->find($value),
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function results(string $search): array
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->when($search !== '', fn ($query) => $query->where('original_filename', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Media $media): array => [$media->id => static::label($media)])
            ->all();
    }

    private static function label(?Media $media): ?string
    {
        if (! $media) {
            return null;
        }

        return sprintf(
            '<span style="display:flex;align-items:center;gap:0.5rem">'
            .'<img src="%s" style="width:28px;height:28px;object-fit:cover;border-radius:0.25rem" alt="" />%s</span>',
            e(Storage::disk($media->disk)->url($media->path)),
            e($media->original_filename),
        );
    }
}
