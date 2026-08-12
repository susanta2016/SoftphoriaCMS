<?php

use App\Enums\MediaCategory;

return [

    /*
    |--------------------------------------------------------------------------
    | Upload rules per category (ADMIN-005)
    |--------------------------------------------------------------------------
    |
    | mimes/max_size drive Laravel's `mimes:`/`max:` validation rules (max_size
    | is in kilobytes, matching Laravel's convention). accepted_mime_types is
    | the real-MIME-string equivalent Filament's FileUpload::acceptedFileTypes()
    | needs — kept alongside mimes (extensions) rather than derived, so both
    | UploadMedia (create) and MediaResource::replaceFileAction() (ADMIN-005
    | review fix) read the exact same list instead of each guessing their own.
    | disk/directory are where StoreUploadedMediaAction/ReplaceMediaFileAction
    | store the file. SVG is intentionally excluded from the image category
    | for Phase 1.
    |
    */

    'categories' => [
        MediaCategory::Image->value => [
            'mimes' => ['jpeg', 'jpg', 'png', 'webp', 'gif'],
            'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max_size' => 5 * 1024,
            'disk' => 'public',
            'directory' => 'media/images',
        ],
        MediaCategory::Document->value => [
            'mimes' => ['pdf'],
            'accepted_mime_types' => ['application/pdf'],
            'max_size' => 10 * 1024,
            'disk' => 'public',
            'directory' => 'media/documents',
        ],
        MediaCategory::Audio->value => [
            'mimes' => ['mp3', 'wav', 'm4a'],
            'accepted_mime_types' => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/m4a', 'audio/x-m4a'],
            'max_size' => 50 * 1024,
            'disk' => 'local',
            'directory' => 'media/audio',
        ],
        MediaCategory::Video->value => [
            'mimes' => ['mp4', 'webm'],
            'accepted_mime_types' => ['video/mp4', 'video/webm'],
            'max_size' => 200 * 1024,
            'disk' => 'local',
            'directory' => 'media/video',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Avatar uploads
    |--------------------------------------------------------------------------
    |
    | Reuses the image category's mimes/size limit but keeps its own disk/
    | directory so existing avatar paths (storage/app/public/avatars) stay
    | valid — see ResolvesAvatarMedia.
    |
    */

    'avatar' => [
        'disk' => 'public',
        'directory' => 'avatars',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image variant generation (GenerateImageVariantsJob)
    |--------------------------------------------------------------------------
    |
    | No spec mandates exact responsive widths — 640/1280 plus a 300x300
    | thumbnail is this project's proposed Phase 1 minimum (approved
    | 2026-08-12). Widths are never upscaled past the original's width.
    | Animated GIFs and images below `min_dimension` are skipped entirely
    | (original is kept, no derived files are generated).
    |
    */

    'variants' => [
        'thumbnail' => ['width' => 300, 'height' => 300],
        'responsive_widths' => [640, 1280],
        'min_dimension' => 50,
        'formats' => [
            'webp' => true,
            'avif' => true,
        ],
    ],

];
