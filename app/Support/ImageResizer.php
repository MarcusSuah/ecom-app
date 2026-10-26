<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Small GD wrapper for product photos. Any photo a seller takes is brought
 * to the gallery format: the main photo fits a 1200 px box (small photos
 * are enlarged to at least MIN_SIZE), and the thumbnail is a centred
 * square of exactly the requested size. Phone photos are turned upright
 * from their EXIF orientation. Output is always JPEG; transparency is
 * flattened onto white.
 */
final class ImageResizer
{
    /** Shortest long side of a main photo, so tiny photos still fill the gallery. */
    public const MIN_SIZE = 600;

    public static function fit(string $sourcePath, int $maxSize, int $quality = 82): string
    {
        $image = self::load($sourcePath);
        [$width, $height] = [imagesx($image), imagesy($image)];
        $longest = max($width, $height);
        $scale = $longest > $maxSize ? $maxSize / $longest : max(1, min($maxSize, self::MIN_SIZE) / $longest);

        return self::encode(
            self::resample($image, 0, 0, $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))),
            $quality,
        );
    }

    /**
     * @param  int<1, max>  $size
     */
    public static function square(string $sourcePath, int $size, int $quality = 80): string
    {
        $image = self::load($sourcePath);
        [$width, $height] = [imagesx($image), imagesy($image)];
        $side = min($width, $height);

        return self::encode(
            self::resample($image, intdiv($width - $side, 2), intdiv($height - $side, 2), $side, $side, $size, $size),
            $quality,
        );
    }

    private static function load(string $path): GdImage
    {
        $contents = file_get_contents($path);
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The image could not be read.');
        }

        return self::upright($image, $path);
    }

    /**
     * Phones store photos sideways and record the turn in EXIF.
     */
    private static function upright(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match (is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private static function resample(GdImage $source, int $sourceX, int $sourceY, int $sourceWidth, int $sourceHeight, int $width, int $height): GdImage
    {
        $target = imagecreatetruecolor($width, $height)
            ?: throw new RuntimeException('The image could not be resized.');
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $width, $height, $sourceWidth, $sourceHeight);

        return $target;
    }

    private static function encode(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
