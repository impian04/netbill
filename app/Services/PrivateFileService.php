<?php

namespace App\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrivateFileService
{
    private const MAX_BYTES = 3 * 1024 * 1024; // 3 MB
    private const MAX_WORK_ORDER_BYTES = 8 * 1024 * 1024; // 8 MB
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];
    private const MAX_DIMENSION = 1600;

    /**
     * Store and strip EXIF from customer KTP image using PHP GD.
     *
     * @param UploadedFile $file
     * @return string Relative storage path on 'local' private disk
     */
    public static function storeKtp(UploadedFile $file): string
    {
        return self::processAndSaveImage($file, self::MAX_BYTES, 'ktp', 'KTP');
    }

    /**
     * Store and strip EXIF from technician work order photo using PHP GD.
     *
     * @param UploadedFile $file
     * @return string Relative storage path on 'local' private disk
     */
    public static function storeWorkOrderPhoto(UploadedFile $file): string
    {
        return self::processAndSaveImage($file, self::MAX_WORK_ORDER_BYTES, 'work_orders', 'foto pekerjaan');
    }

    /**
     * Process image, downscale if needed, strip EXIF metadata, and save to private disk.
     */
    private static function processAndSaveImage(UploadedFile $file, int $maxBytes, string $folder, string $label): string
    {
        if ($file->getSize() > $maxBytes) {
            $mb = round($maxBytes / (1024 * 1024));
            throw new InvalidArgumentException("Ukuran berkas {$label} maksimal {$mb} MB.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());

        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException("Tipe berkas {$label} tidak valid. Gunakan format JPG, PNG, atau WebP.");
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException("Ekstensi PHP GD tidak tersedia untuk memproses gambar {$label}.");
        }

        $image = match ($realMime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png'  => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : null,
            default      => null,
        };

        if (! $image) {
            throw new RuntimeException("Gagal memproses gambar {$label}. Pastikan berkas gambar tidak rusak.");
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        // Downscale maintaining aspect ratio if max dimension > 1600 px
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            if ($width >= $height) {
                $newWidth  = self::MAX_DIMENSION;
                $newHeight = (int) round(($height / $width) * self::MAX_DIMENSION);
            } else {
                $newHeight = self::MAX_DIMENSION;
                $newWidth  = (int) round(($width / $height) * self::MAX_DIMENSION);
            }

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $white);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $uuid = (string) Str::uuid();
        $relativePath = "{$folder}/{$uuid}.jpg";

        Storage::disk('local')->makeDirectory($folder);
        $fullPath = Storage::disk('local')->path($relativePath);

        // Save as JPEG with quality 85 — completely strips EXIF and camera metadata
        imagejpeg($image, $fullPath, 85);
        imagedestroy($image);

        return $relativePath;
    }

    /**
     * Stream private work order photo with no-cache and nosniff headers.
     */
    public static function streamWorkOrderPhoto(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404, 'Berkas foto tidak ditemukan.');
        }

        $fullPath = $disk->path($path);

        $response = response()->file($fullPath, [
            'Content-Type'           => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => 'inline; filename="photo.jpg"',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');

        return $response;
    }

    /**
     * Stream private file with no-cache and nosniff headers.
     *
     * @param string $path
     * @return BinaryFileResponse
     */
    public static function streamKtp(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404, 'Berkas KTP tidak ditemukan.');
        }

        $fullPath = $disk->path($path);

        $response = response()->file($fullPath, [
            'Content-Type'           => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => 'inline; filename="ktp.jpg"',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');

        return $response;
    }

    /**
     * Delete file from private disk.
     *
     * @param string|null $path
     * @return void
     */
    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Buat berkas gambar demo nyata (GD, 800x600 teks "DEMO") untuk seeder lokal (Fase 17f).
     */
    public static function createDemoPhoto(string $path, string $text = 'DEMO', int $width = 800, int $height = 600): string
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return $path;
        }

        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor($width, $height);
            $bgColor = imagecolorallocate($im, 45, 55, 72);
            $textColor = imagecolorallocate($im, 240, 245, 250);
            $subColor = imagecolorallocate($im, 160, 174, 192);

            imagefilledrectangle($im, 0, 0, $width, $height, $bgColor);

            $font = 5;
            $textWidth = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);
            $x = (int) (($width - $textWidth) / 2);
            $y = (int) (($height - $textHeight) / 2) - 10;
            imagestring($im, $font, $x, $y, $text, $textColor);

            $subText = 'NetBill Demo Foto (800x600)';
            $subWidth = imagefontwidth(3) * strlen($subText);
            $subX = (int) (($width - $subWidth) / 2);
            imagestring($im, 3, $subX, $y + 30, $subText, $subColor);

            ob_start();
            imagejpeg($im, null, 85);
            $jpegData = ob_get_clean();
            imagedestroy($im);
        } else {
            $jpegData = base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8U'
                . 'HRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgN'
                . 'DRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy'
                . 'MjL/wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAA'
                . 'AAAAAAAAAAAAAP/EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA'
                . '/9oADAMBAAIRAxEAPwCwABmX/9k='
            );
        }

        $disk->put($path, $jpegData);
        return $path;
    }
}
