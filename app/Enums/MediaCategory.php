<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * ADMIN-005: derived from media.mime_type (already indexed), never stored —
 * the category is fully determined by the MIME prefix, so a dedicated
 * column would just duplicate existing data (see Media::category()).
 */
enum MediaCategory: string implements HasColor, HasIcon, HasLabel
{
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';

    public static function fromMimeType(string $mimeType): ?self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'audio/') => self::Audio,
            str_starts_with($mimeType, 'video/') => self::Video,
            $mimeType === 'application/pdf' => self::Document,
            default => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Document => 'Document',
            self::Audio => 'Audio',
            self::Video => 'Video',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Image => 'success',
            self::Document => 'gray',
            self::Audio => 'warning',
            self::Video => 'info',
        };
    }

    public function getIcon(): string|BackedEnum
    {
        return match ($this) {
            self::Image => Heroicon::OutlinedPhoto,
            self::Document => Heroicon::OutlinedDocument,
            self::Audio => Heroicon::OutlinedMusicalNote,
            self::Video => Heroicon::OutlinedVideoCamera,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->getLabel()])
            ->all();
    }

    /**
     * Convenience accessors over config('media.categories') — still the sole
     * source of truth (see config/media.php) — so reusable UI built on top
     * of a category (MediaPicker, RichEditorMediaAttachments) reads the same
     * values as StoreUploadedMediaAction/UploadMedia instead of duplicating
     * the config lookups.
     */
    public function diskName(): string
    {
        return config("media.categories.{$this->value}.disk", 'public');
    }

    public function directory(): string
    {
        return config("media.categories.{$this->value}.directory", 'media/misc');
    }

    public function maxSizeKb(): ?int
    {
        return config("media.categories.{$this->value}.max_size");
    }

    /**
     * @return array<string>
     */
    public function acceptedMimeTypes(): array
    {
        return config("media.categories.{$this->value}.accepted_mime_types", []);
    }

    public function visibility(): string
    {
        return $this->diskName() === 'public' ? 'public' : 'protected';
    }
}
