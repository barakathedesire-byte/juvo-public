<?php

namespace App\Http\Controllers\Admin\Homepage\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesHomepageMedia
{
    /**
     * Store an uploaded image on the public disk and return its relative path.
     */
    protected function storeImage(?UploadedFile $file, string $folder): ?string
    {
        if (! $file) {
            return null;
        }

        return store_optimized_image($file, "homepage/{$folder}");
    }

    protected function deleteImage(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://']) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Destination may be an absolute URL or an internal path, never a script scheme.
     */
    protected function assertDestination(?string $value, string $field = 'cta_url'): void
    {
        if (! $value) {
            return;
        }

        if (Str::startsWith($value, ['javascript:', 'data:', 'vbscript:'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'The destination must be an internal path or a valid http(s) URL.',
            ]);
        }
    }
}
