<?php

namespace App\Services\Tips;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures and videos of the tech tips, on the private "local" disk (no storage:link needed, works the same on XAMPP).
 * Files get random names and never change, so the tablet may cache them for good. Big photos are shrunk with GD
 * so the tablet loads them quickly; GIFs (animations) and videos are kept as they are.
 * A file is deleted when no tip uses it any more (sweep()).
 */
class TipMedia
{
    public const DIR = 'tech-tips';

    /** File name of an uploaded picture / video: what may appear in an article and in the /tips/media URL. */
    public const FILE_PATTERN = '[A-Za-z0-9]{32}\.(?:webp|jpg|jpeg|png|gif|mp4|webm)';

    public const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    public function store(UploadedFile $file): string
    {
        $extension = strtolower($file->extension());
        if (in_array($extension, [...self::VIDEO_EXTENSIONS, 'gif'], true)) {
            return $file->storeAs(self::DIR, Str::random(32).'.'.$extension, 'local');
        }

        $maxWidth = config('tips.image_max_width');
        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;

        if ($image === false) {
            return $file->storeAs(self::DIR, Str::random(32).'.'.$extension, 'local');
        }

        imagepalettetotruecolor($image); // WebP can't be written from palette (GIF / 8-bit PNG) pictures
        imagesavealpha($image, true);
        $image = $this->rotateUpright($image, $file);

        if (imagesx($image) > $maxWidth) {
            $resized = imagescale($image, $maxWidth, -1, IMG_BICUBIC);
            imagedestroy($image);
            $image = $resized;
        }

        // WebP keeps PNG transparency and is small; JPEG when this PHP can't write WebP.
        $webp = function_exists('imagewebp');
        if (! $webp) {
            $image = $this->flatten($image);
        }
        ob_start();
        $webp ? imagewebp($image, null, 82) : imagejpeg($image, null, 85);
        $data = ob_get_clean();
        imagedestroy($image);

        $path = self::DIR.'/'.Str::random(32).($webp ? '.webp' : '.jpg');
        Storage::disk('local')->put($path, $data);

        return $path;
    }

    /** "tech-tips/<name>" for a file name that may be used in an article, null for anything else. */
    public function pathOf(mixed $value): ?string
    {
        $name = is_string($value) ? basename($value) : '';
        if (! preg_match('/^'.self::FILE_PATTERN.'$/', $name) || ! Storage::disk('local')->exists(self::DIR.'/'.$name)) {
            return null;
        }

        return self::DIR.'/'.$name;
    }

    public function url(?string $path): ?string
    {
        return $path ? route('tips.media', basename($path)) : null;
    }

    public function isVideo(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true);
    }

    /**
     * Deletes files no tip uses any more: the given ones (a tip was edited / deleted) and uploads
     * that were never saved into a tip (editor closed without saving) after a day.
     *
     * @param  string[]  $candidates
     */
    public function sweep(array $candidates = []): void
    {
        $disk = Storage::disk('local');
        $used = array_flip(app(TipSlides::class)->usedPaths());
        $dayAgo = now()->subDay()->getTimestamp();

        foreach (array_unique([...$candidates, ...$disk->files(self::DIR)]) as $path) {
            if (isset($used[$path]) || ! $disk->exists($path)) {
                continue;
            }
            if (in_array($path, $candidates, true) || $disk->lastModified($path) < $dayAgo) {
                $disk->delete($path);
            }
        }
    }

    /** Phone photos are often saved sideways with an EXIF note "turn me"; GD ignores it, so turn them here. */
    private function rotateUpright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }
        $angle = match ((int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };
        if ($angle === 0 || ! ($rotated = imagerotate($image, $angle, 0))) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    /** JPEG has no transparency: put the picture on white. */
    private function flatten(\GdImage $image): \GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $flat;
    }
}
