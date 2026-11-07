<?php

namespace App\Services\Tips;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
    public const FILE_PATTERN = '[A-Za-z0-9]{32}\.(?:webp|jpg|jpeg|png|gif|svg|mp4|webm)';

    public const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /** Biggest picture downloaded by "picture from a link". */
    public const LINK_MAX_MB = 12;

    public function store(UploadedFile $file): string
    {
        // Drawings (SVG) are kept as vectors, cleaned of anything that could run or load from elsewhere.
        if ($this->isSvgFile($file->getRealPath())) {
            $svg = $this->cleanSvg((string) file_get_contents($file->getRealPath()));
            if ($svg === null) {
                throw new \InvalidArgumentException('Unreadable SVG file.');
            }
            $path = self::DIR.'/'.Str::random(32).'.svg';
            Storage::disk('local')->put($path, $svg);

            return $path;
        }

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

    /**
     * "Picture from a link": downloads the picture into a temp file, which then goes through store() like an upload,
     * so the tablet never depends on the other site (the gym may have no internet). Only public http(s) addresses:
     * the server must not be used to read the LAN / itself. Returns null with $error set when it doesn't work.
     */
    public function download(string $url, ?string &$error = null): ?UploadedFile
    {
        $maxBytes = self::LINK_MAX_MB * 1024 * 1024;
        if (! $this->isPublicUrl($url)) {
            $error = __('Use a public http(s) link to a picture.');

            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'tip');
        try {
            // Many image servers / CDNs answer 403 to clients without a browser-like User-Agent.
            $response = Http::timeout(20)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 ClimbersHub',
                'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif,image/*;q=0.8,*/*;q=0.5',
            ])->withOptions([
                'sink' => $temp,
                'allow_redirects' => ['max' => 5, 'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) {
                        if (! $this->isPublicUrl((string) $uri)) {
                            throw new \RuntimeException('Redirect to a private address');
                        }
                    }],
                'progress' => function ($total, $loaded) use ($maxBytes) {
                    if ($total > $maxBytes || $loaded > $maxBytes) {
                        throw new \RuntimeException('Too big');
                    }
                },
            ])->get($url);
        } catch (\Throwable) {
            $response = null;
        }

        if (! $response?->successful()) {
            @unlink($temp);
            $error = __('Could not download the picture from this link.');

            return null;
        }

        clearstatcache(true, $temp);
        if (filesize($temp) <= $maxBytes && $this->isSvgFile($temp)) {
            return new UploadedFile($temp, 'link.svg', 'image/svg+xml', null, true);
        }
        $type = @getimagesize($temp)[2] ?? null;
        $extension = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$type] ?? null;
        if (! $extension || filesize($temp) > $maxBytes) {
            @unlink($temp);
            $error = __('The link is not a picture (JPG, PNG, WebP, GIF or SVG).');

            return null;
        }

        return new UploadedFile($temp, 'link.'.$extension, image_type_to_mime_type($type), null, true);
    }

    /** An SVG drawing: XML whose first element is <svg>. */
    public function isSvgFile(string $file): bool
    {
        $head = (string) @file_get_contents($file, false, null, 0, 4096);

        return (bool) preg_match('/^\s*(?:\xEF\xBB\xBF)?(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*|<!DOCTYPE[^>]*>\s*)*<svg[\s>]/is', $head);
    }

    public function isSvg(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * SVG without scripts, event attributes, embedded HTML and links to other files (only #ids and data: pictures
     * stay), so it is just a drawing. The tablet shows it in <img> (no scripts there anyway); KioskController::media
     * also sends a strict Content-Security-Policy when it is opened on its own. Null when it isn't readable SVG.
     */
    public function cleanSvg(string $svg): ?string
    {
        $svg = preg_replace('/<!DOCTYPE[^>\[]*(\[[^\]]*\])?\s*>/is', '', $svg); // no entities / external DTDs
        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $ok || strtolower($doc->documentElement?->localName ?? '') !== 'svg') {
            return null;
        }

        $xpath = new \DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//*[local-name()="script" or local-name()="foreignObject" or local-name()="iframe" or local-name()="object" or local-name()="embed" or local-name()="audio" or local-name()="video"]')) as $node) {
            $node->parentNode->removeChild($node);
        }
        foreach (iterator_to_array($xpath->query('//processing-instruction()')) as $node) {
            $node->parentNode->removeChild($node);
        }
        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->localName);
                $value = trim($attribute->value);
                $isLink = in_array($name, ['href', 'src'], true);
                if (str_starts_with($name, 'on')
                    || ($isLink && ! str_starts_with($value, '#') && ! preg_match('#^data:image/(png|jpe?g|gif|webp);#i', $value))
                    || preg_match('/url\(\s*[\'"]?\s*(?!#|data:image\/)/i', $value)
                    || preg_match('/javascript:/i', $value)) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
        foreach (iterator_to_array($xpath->query('//*[local-name()="style"]')) as $style) {
            $css = preg_replace('/@import[^;]*;?/i', '', $style->textContent);
            $style->textContent = preg_replace('/url\(\s*[\'"]?\s*(?!#|data:image\/)[^)]*\)/i', 'none', $css);
        }

        return $doc->saveXML($doc->documentElement);
    }

    /** http(s) address whose host is (or resolves to) a public IP. */
    private function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        return $ips && collect($ips)->every(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
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
