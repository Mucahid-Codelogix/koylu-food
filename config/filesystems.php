<?php

$objectStorageBucket = env('AWS_BUCKET');
$objectStorageRegion = env('AWS_DEFAULT_REGION');

$usesObjectStorage = filled($objectStorageBucket)
    && filled($objectStorageRegion)
    && filled(env('AWS_ACCESS_KEY_ID'));

$objectStorageEndpoint = env('AWS_ENDPOINT');

if ($usesObjectStorage) {
    if (blank($objectStorageEndpoint)) {
        $objectStorageEndpoint = "https://{$objectStorageRegion}.digitaloceanspaces.com";
    }

    if (str_contains($objectStorageEndpoint, "{$objectStorageBucket}.")) {
        $objectStorageEndpoint = "https://{$objectStorageRegion}.digitaloceanspaces.com";
    }
}

$objectStorageUrl = env('AWS_URL');

if ($usesObjectStorage && blank($objectStorageUrl)) {
    $objectStorageUrl = "https://{$objectStorageBucket}.{$objectStorageRegion}.digitaloceanspaces.com";
}

$uploadDisk = env('FILESYSTEM_UPLOAD_DISK');

if (blank($uploadDisk)) {
    $uploadDisk = (env('APP_ENV') === 'production' && $usesObjectStorage) ? 'spaces' : 'public';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload disk (product images, invoices, signatures)
    |--------------------------------------------------------------------------
    |
    | Locally: always public. Production: spaces when AWS credentials are set.
    |
    */

    'upload_disk' => $uploadDisk,

    'upload_env' => env('UPLOAD_ENV', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => $objectStorageUrl,
            'endpoint' => $objectStorageEndpoint,
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'spaces' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'endpoint' => $objectStorageEndpoint,
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => $objectStorageUrl,
            'visibility' => 'public',
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
