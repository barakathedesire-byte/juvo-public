<?php

namespace App\Services\Kyc;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;

class KycVerificationService
{
    protected array $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
    protected array $allowedDocumentMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public function verifyRider(\App\Models\Rider\Rider $rider): array
    {
        $documents = $rider->kyc_documents ?? [];
        $results = [];
        $passed = 0;
        $total = 0;

        foreach ($documents as $type => $path) {
            if (! $path) {
                continue;
            }

            $total++;
            $result = $this->verifyStoredFile($path, 'private', (string) $type);
            $results[$type] = $result;

            if ($result['status'] === 'passed') {
                $passed++;
            }
        }

        $score = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

        $rider->update([
            'kyc_checks' => $results,
            'kyc_score' => $score,
        ]);

        return [
            'status' => $total > 0 && $passed === $total ? 'passed' : ($passed === 0 && $total > 0 ? 'failed' : 'needs_review'),
            'score' => $score,
            'checks' => $results,
        ];
    }

    public function verifyVendorDocument(\App\Models\VendorVerificationDocument $document): array
    {
        $result = $this->verifyStoredFile($document->file_path, $document->disk ?: 'local', $document->type);

        $document->update([
            'checks' => $result['checks'],
            'score' => $result['score'],
            'status' => $result['status'] === 'failed' ? 'rejected' : 'pending',
            'rejection_reason' => $result['status'] === 'failed' ? implode('; ', $result['reasons']) : null,
        ]);

        return $result;
    }

    public function verifyUploadedFile(UploadedFile $file, string $type = 'document'): array
    {
        $checks = [];
        $reasons = [];
        $score = 0;
        $maxScore = 0;

        $this->checkFileBasics($file, $checks, $reasons, $score, $maxScore);
        $mime = (string) $file->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            $this->checkImage($file->getRealPath(), $checks, $reasons, $score, $maxScore);
        } elseif ($mime === 'application/pdf') {
            $this->checkPdf($file->getRealPath(), $checks, $reasons, $score, $maxScore);
        }

        return $this->finalize($checks, $reasons, $score, $maxScore);
    }

    protected function verifyStoredFile(string $path, string $disk, string $type): array
    {
        $checks = [];
        $reasons = [];
        $score = 0;
        $maxScore = 0;

        try {
            $contents = Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'file_exists', 'status' => 'failed', 'message' => 'Stored file could not be read.'];
            $reasons[] = 'Stored file could not be read.';

            return $this->finalize($checks, $reasons, 0, 1);
        }

        $fullPath = Storage::disk($disk)->path($path);
        $mime = mime_content_type($fullPath) ?: '';

        $checks[] = ['name' => 'file_exists', 'status' => 'passed', 'message' => 'Stored file is readable.'];
        $score++;
        $maxScore++;

        if (! in_array($mime, $this->allowedDocumentMimes, true)) {
            $checks[] = ['name' => 'file_type', 'status' => 'failed', 'message' => "Unsupported MIME type: {$mime}."];
            $reasons[] = "Unsupported file type: {$mime}.";
        } else {
            $checks[] = ['name' => 'file_type', 'status' => 'passed', 'message' => "Accepted file type: {$mime}."];
            $score++;
        }
        $maxScore++;

        if (str_starts_with($mime, 'image/')) {
            $this->checkImage($fullPath, $checks, $reasons, $score, $maxScore);
        } elseif ($mime === 'application/pdf') {
            $this->checkPdf($fullPath, $checks, $reasons, $score, $maxScore);
        }

        return $this->finalize($checks, $reasons, $score, $maxScore);
    }

    protected function checkFileBasics(UploadedFile $file, array &$checks, array &$reasons, int &$score, int &$maxScore): void
    {
        $maxScore++;
        if (! $file->isValid()) {
            $checks[] = ['name' => 'upload', 'status' => 'failed', 'message' => 'Upload failed.'];
            $reasons[] = 'Upload failed.';

            return;
        }

        $checks[] = ['name' => 'upload', 'status' => 'passed', 'message' => 'File uploaded successfully.'];
        $score++;
    }

    protected function checkImage(string $path, array &$checks, array &$reasons, int &$score, int &$maxScore): void
    {
        $info = @getimagesize($path);

        $maxScore++;
        if (! $info) {
            $checks[] = ['name' => 'image_dimensions', 'status' => 'failed', 'message' => 'Image could not be read.'];
            $reasons[] = 'Image could not be read.';

            return;
        }

        [$width, $height] = $info;
        $maxDimension = 4096;
        $maxPixels = 16000000;
        $tooLarge = $width > $maxDimension || $height > $maxDimension || ($width * $height) > $maxPixels;

        if ($width < 300 || $height < 300) {
            $checks[] = ['name' => 'image_dimensions', 'status' => 'failed', 'message' => "Image is too small ({$width}x{$height})."];
            $reasons[] = 'Image resolution is too low.';
        } elseif ($tooLarge) {
            $checks[] = ['name' => 'image_dimensions', 'status' => 'failed', 'message' => "Image is too large ({$width}x{$height})."];
            $reasons[] = 'Image resolution is too high.';
        } else {
            $checks[] = ['name' => 'image_dimensions', 'status' => 'passed', 'message' => "Image resolution is {$width}x{$height}."];
            $score++;
        }

        $maxScore++;
        if ($tooLarge) {
            $checks[] = ['name' => 'image_sharpness', 'status' => 'needs_review', 'message' => 'Sharpness was not evaluated for an oversized image.'];
        } else {
            $blurScore = $this->laplacianVariance($path);
            if ($blurScore === null) {
                $checks[] = ['name' => 'image_sharpness', 'status' => 'needs_review', 'message' => 'Sharpness could not be evaluated.'];
            } elseif ($blurScore < 80) {
                $checks[] = ['name' => 'image_sharpness', 'status' => 'failed', 'message' => "Image appears blurry (score {$blurScore})."];
                $reasons[] = 'Image appears blurry.';
            } else {
                $checks[] = ['name' => 'image_sharpness', 'status' => 'passed', 'message' => "Image sharpness score is {$blurScore}."];
                $score++;
            }
        }

        $maxScore++;
        $exif = @exif_read_data($path);
        if (is_array($exif) && ! empty($exif)) {
            $checks[] = ['name' => 'image_metadata', 'status' => 'passed', 'message' => 'Image metadata is present.'];
            $score++;
        } else {
            $checks[] = ['name' => 'image_metadata', 'status' => 'needs_review', 'message' => 'No image metadata found.'];
        }
    }

    protected function checkPdf(string $path, array &$checks, array &$reasons, int &$score, int &$maxScore): void
    {
        $maxScore++;
        $text = '';

        if (is_executable('/usr/bin/pdftotext')) {
            $process = Process::run(['/usr/bin/pdftotext', '-layout', $path, '-']);
            if ($process->successful()) {
                $text = trim($process->output());
            }
        }

        if (strlen($text) < 20) {
            $checks[] = ['name' => 'pdf_text', 'status' => 'failed', 'message' => 'No readable text was extracted from the PDF.'];
            $reasons[] = 'PDF text extraction failed.';
        } else {
            $checks[] = ['name' => 'pdf_text', 'status' => 'passed', 'message' => 'Readable text was extracted from the PDF.'];
            $score++;
        }
    }

    protected function laplacianVariance(string $path): ?int
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image) {
            return null;
        }

        $maxAnalysisSide = 512;
        $width = imagesx($image);
        $height = imagesy($image);
        $longestSide = max($width, $height);

        if ($longestSide > $maxAnalysisSide) {
            $ratio = $maxAnalysisSide / $longestSide;
            $scaled = imagescale(
                $image,
                max(1, (int) round($width * $ratio)),
                max(1, (int) round($height * $ratio)),
                IMG_BILINEAR_FIXED
            );

            if (! $scaled) {
                imagedestroy($image);

                return null;
            }

            imagedestroy($image);
            $image = $scaled;
            $width = imagesx($image);
            $height = imagesy($image);
        }

        if ($width < 3 || $height < 3) {
            imagedestroy($image);

            return null;
        }

        $gray = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray[($y * $width) + $x] = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
            }
        }

        imagedestroy($image);

        $count = 0;
        $sum = 0;
        $sumSquares = 0;

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $laplacian = (
                    -1 * $gray[(($y - 1) * $width) + ($x - 1)]
                    -1 * $gray[(($y - 1) * $width) + $x]
                    -1 * $gray[(($y - 1) * $width) + ($x + 1)]
                    -1 * $gray[($y * $width) + ($x - 1)]
                    + 8 * $gray[($y * $width) + $x]
                    -1 * $gray[($y * $width) + ($x + 1)]
                    -1 * $gray[(($y + 1) * $width) + ($x - 1)]
                    -1 * $gray[(($y + 1) * $width) + $x]
                    -1 * $gray[(($y + 1) * $width) + ($x + 1)]
                );

                $count++;
                $sum += $laplacian;
                $sumSquares += $laplacian * $laplacian;
            }
        }

        if ($count === 0) {
            return null;
        }

        $mean = $sum / $count;
        $variance = ($sumSquares / $count) - ($mean * $mean);

        return max(0, (int) round($variance));
    }

    protected function finalize(array $checks, array $reasons, int $score, int $maxScore): array
    {
        $status = 'needs_review';

        if ($maxScore > 0 && $score === $maxScore && count($reasons) === 0) {
            $status = 'passed';
        } elseif (count($reasons) > 0) {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'score' => $maxScore > 0 ? (int) round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
