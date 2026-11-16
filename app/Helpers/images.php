<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

if (! function_exists('remove_white_background')) {
    function remove_white_background($source)
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $cutout = imagecreatetruecolor($width, $height);

        imagealphablending($cutout, false);
        imagesavealpha($cutout, true);

        $transparent = imagecolorallocatealpha($cutout, 0, 0, 0, 127);
        imagefill($cutout, 0, 0, $transparent);

        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;
        $hasSubject = false;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($source, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($r > 245 && $g > 245 && $b > 245) {
                    continue;
                }

                imagesetpixel($cutout, $x, $y, imagecolorallocatealpha($cutout, $r, $g, $b, 0));
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
                $hasSubject = true;
            }
        }

        if (! $hasSubject) {
            imagedestroy($cutout);

            return $source;
        }

        $padding = 12;
        $cropX = max(0, $minX - $padding);
        $cropY = max(0, $minY - $padding);
        $cropWidth = min($width - $cropX, ($maxX - $minX + 1) + ($padding * 2));
        $cropHeight = min($height - $cropY, ($maxY - $minY + 1) + ($padding * 2));

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagefill($cropped, 0, 0, $transparent);
        imagecopy($cropped, $cutout, 0, 0, $cropX, $cropY, $cropWidth, $cropHeight);

        imagedestroy($cutout);

        return $cropped;
    }
}

if (! function_exists('store_optimized_image')) {
    function store_optimized_image(UploadedFile $file, string $folder, int $maxWidth = 1600, int $quality = 82): string
    {
        $folder = trim($folder, '/');

        if ($folder === 'products' || str_starts_with($folder, 'products/')) {
            $errors = product_image_errors($file);

            if ($errors !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'images' => $errors,
                ]);
            }
        }

        $directory = storage_path('app/public/'.$folder);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $mime = (string) $file->getMimeType();

        if (! str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
            return $file->store($folder, 'public');
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source) {
            return $file->store($folder, 'public');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            imagedestroy($source);
            $source = $resized;
        }

        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $base = $base ?: 'image';
        $filename = $base.'-'.Str::random(12).'.webp';
        $path = $directory.'/'.$filename;

        if (! imagewebp($source, $path, $quality)) {
            imagedestroy($source);

            return $file->store($folder, 'public');
        }

        imagedestroy($source);

        return $folder.'/'.$filename;
    }
}

if (! function_exists('product_image_errors')) {
    function product_image_errors(\Illuminate\Http\UploadedFile $file): array
    {
        $errors = [];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowed, true)) {
            $errors[] = 'Use a JPG, PNG, or WebP image.';

            return $errors;
        }

        $info = @getimagesize($file->getRealPath());

        if (! $info) {
            $errors[] = 'The image could not be read.';

            return $errors;
        }

        [$width, $height] = $info;

        if ($width < 600 || $height < 600) {
            $errors[] = 'Images must be at least 600x600 pixels.';

            return $errors;
        }

        if ($width > 4096 || $height > 4096) {
            $errors[] = 'Images must be no larger than 4096x4096 pixels.';

            return $errors;
        }

        $ratio = $width / max(1, $height);

        if ($ratio > 3 || $ratio < (1 / 3)) {
            $errors[] = 'Use an image with a reasonable aspect ratio.';

            return $errors;
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source) {
            $errors[] = 'The image could not be processed.';

            return $errors;
        }

        $maxAnalysisSide = 768;
        $longestSide = max($width, $height);

        if ($longestSide > $maxAnalysisSide) {
            $scale = $maxAnalysisSide / $longestSide;
            $scaled = imagescale(
                $source,
                max(1, (int) round($width * $scale)),
                max(1, (int) round($height * $scale)),
                IMG_BILINEAR_FIXED
            );

            if (! $scaled) {
                imagedestroy($source);

                return ['The image could not be processed.'];
            }

            imagedestroy($source);
            $source = $scaled;
            $width = imagesx($source);
            $height = imagesy($source);
        }

        $grayAt = static function (int $px, int $py) use ($source): int {
            $rgb = imagecolorat($source, $px, $py);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            return (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
        };

        $totalLuminance = 0;
        $pixels = 0;
        $sharpnessSumSquares = 0;
        $sharpnessCount = 0;

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $laplacian = (
                    -1 * $grayAt($x - 1, $y - 1)
                    -1 * $grayAt($x, $y - 1)
                    -1 * $grayAt($x + 1, $y - 1)
                    -1 * $grayAt($x - 1, $y)
                    + 8 * $grayAt($x, $y)
                    -1 * $grayAt($x + 1, $y)
                    -1 * $grayAt($x - 1, $y + 1)
                    -1 * $grayAt($x, $y + 1)
                    -1 * $grayAt($x + 1, $y + 1)
                );

                $totalLuminance += $grayAt($x, $y);
                $pixels++;
                $sharpnessSumSquares += $laplacian * $laplacian;
                $sharpnessCount++;
            }
        }

        $sharpness = $sharpnessCount > 0 ? (int) round($sharpnessSumSquares / $sharpnessCount) : 0;
        $averageLuminance = $pixels > 0 ? $totalLuminance / $pixels : 0;

        imagedestroy($source);

        if ($sharpness < 500) {
            $errors[] = 'The image is too blurry.';
        }

        if ($averageLuminance < 45) {
            $errors[] = 'The image is too dark.';
        }

        if ($averageLuminance > 245) {
            $errors[] = 'The image is overexposed.';
        }

        return $errors;
    }
}


if (! function_exists('store_product_image')) {
    function store_product_image(\Illuminate\Http\UploadedFile $file, bool $removeBackground = false): string
    {
        $path = store_optimized_image($file, 'products');

        if (! $removeBackground) {
            return $path;
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        $source = @imagecreatefromstring((string) file_get_contents($fullPath));

        if (! $source) {
            return $path;
        }

        $processed = remove_white_background($source);

        if ($processed === $source) {
            return $path;
        }

        imagedestroy($source);

        $newRelativePath = trim(dirname($path), '.').'/'.pathinfo($path, PATHINFO_FILENAME).'-nobg-'.\Illuminate\Support\Str::random(10).'.webp';
        $newRelativePath = ltrim(str_replace('//', '/', $newRelativePath), '/');
        $newFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($newRelativePath);

        if (! is_dir(dirname($newFullPath))) {
            mkdir(dirname($newFullPath), 0755, true);
        }

        if (! imagewebp($processed, $newFullPath, 90)) {
            imagedestroy($processed);

            return $path;
        }

        imagedestroy($processed);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);

        return $newRelativePath;
    }
}
