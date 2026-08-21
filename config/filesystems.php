<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Livewire's *temporary* upload staging area only (config/livewire.php
        // → temporary_file_upload.disk, via LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK)
        // — deliberately rooted on the container's own local filesystem, not
        // under storage_path() (which is bind-mounted from the host, and on
        // Windows/WSL2 that bind mount is a 9p filesystem — Filament's
        // FileUpload writes the initial upload here, then immediately
        // re-reads it for size/mime validation; that write-then-stat sequence
        // was unreliable enough over 9p to silently drop the file body while
        // still writing Livewire's own metadata sidecar, producing
        // Flysystem's "Unable to retrieve the file_size" error on every
        // upload). sys_get_temp_dir() resolves to a normal fast local path in
        // any environment (this container's own /tmp, or a real server's OS
        // temp dir) — this is a correctness fix generally, not a Docker-only
        // workaround. Final, permanent storage (`local`/`public` above) is
        // untouched and still lives under storage_path() as before.
        'livewire-tmp' => [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/livewire-uploads',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
