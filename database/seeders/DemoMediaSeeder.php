<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShopProduct;
use App\Models\ShopProductImage;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo photos and details for every business on every marketplace, so the
 * storefront looks full without uploading anything: at least IMAGES photos
 * per wholesale product and shop item (generated, colourful placeholders),
 * a logo per company, and descriptions, cities and phones where missing.
 * Safe to run again: it only tops up what is missing.
 *
 *   php artisan db:seed --class=DemoMediaSeeder
 */
class DemoMediaSeeder extends Seeder
{
    public const IMAGES = 8;

    private const WIDTH = 800;

    private const HEIGHT = 600;

    private const THUMB = 400;

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (Tenant::query()->get() as $tenant) {
            app(CurrentTenant::class)->runAs($tenant, function () use ($disk): void {
                $this->companies($disk);
                $this->products($disk);
                $this->shopItems($disk);
            });
        }
    }

    private function companies(Filesystem $disk): void
    {
        Company::query()->lazyById(100)->each(function (Company $company) use ($disk): void {
            $changes = array_filter([
                'description' => $company->description ? null : fake()->paragraph(3),
                'city' => $company->city ? null : fake()->randomElement(['Kigali', 'Huye', 'Musanze', 'Rubavu', 'Nairobi', 'Kampala']),
                'phone' => $company->phone ? null : '+250 78'.fake()->numerify('# ### ###'),
            ]);

            if (! $company->logo) {
                $path = "company-logos/demo-{$company->id}.jpg";
                $disk->put($path, $this->encode($this->draw($company->slug, 0, 256, 256, $this->initials($company->name))));
                $changes['logo'] = $path;
            }

            if ($changes !== []) {
                $company->forceFill($changes)->saveQuietly();
            }
        });
    }

    private function products(Filesystem $disk): void
    {
        Product::query()->withCount('images')->lazyById(100)->each(function (Product $product) use ($disk): void {
            if (! $product->description) {
                $product->forceFill(['description' => fake()->paragraphs(2, true)])->saveQuietly();
            }

            $this->topUp($disk, $product->images(), (int) $product->images_count, "products/{$product->id}", $product->slug, $product->name);
        });
    }

    private function shopItems(Filesystem $disk): void
    {
        ShopProduct::query()->withCount('images')->lazyById(100)->each(function (ShopProduct $item) use ($disk): void {
            $first = $this->topUp($disk, $item->images(), (int) $item->images_count, "shop-products/{$item->shop_id}/{$item->id}", $item->name, $item->name);

            if (! $item->image && $first !== null) {
                $item->forceFill(['image' => $first])->saveQuietly();
            }
        });
    }

    /**
     * Adds photos until the gallery has IMAGES of them.
     *
     * @param  HasMany<ProductImage, Product>|HasMany<ShopProductImage, ShopProduct>  $images
     * @return string|null The first new thumbnail, for listings that keep a featured image.
     */
    private function topUp(Filesystem $disk, HasMany $images, int $existing, string $folder, string $seed, string $label): ?string
    {
        $first = null;
        $mark = $this->initials($label);

        for ($position = $existing; $position < self::IMAGES; $position++) {
            $name = Str::lower(Str::ulid()).'.jpg';
            $path = "{$folder}/{$name}";
            $thumbnail = "{$folder}/thumbs/{$name}";

            $disk->put($path, $this->encode($this->draw($seed, $position, self::WIDTH, self::HEIGHT, $mark)));
            $disk->put($thumbnail, $this->encode($this->draw($seed, $position, self::THUMB, self::THUMB, $mark)));
            $images->create(['path' => $path, 'thumbnail_path' => $thumbnail, 'position' => $position]);

            $first ??= $thumbnail;
        }

        return $first;
    }

    /**
     * A placeholder photo: the item's own hue (stable per seed), a few soft
     * shapes that change with each photo, and its initials.
     *
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function draw(string $seed, int $variant, int $width, int $height, string $mark): GdImage
    {
        $image = imagecreatetruecolor($width, $height) ?: throw new RuntimeException('GD could not create an image.');
        $hue = (crc32($seed) + $variant * 37) % 360;

        [$r, $g, $b] = $this->hsl($hue, 0.45, 0.82);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));

        mt_srand(crc32($seed.$variant));
        for ($i = 0; $i < 4; $i++) {
            [$r, $g, $b] = $this->hsl(($hue + mt_rand(-25, 25) + 360) % 360, 0.5, mt_rand(45, 75) / 100);
            $size = (int) (min($width, $height) * mt_rand(30, 75) / 100);
            imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), $size, $size, (int) imagecolorallocatealpha($image, $r, $g, $b, 50));
        }

        // Initials, drawn small with a built-in font and scaled up.
        $text = imagecreatetruecolor(strlen($mark) * 9 + 4, 18) ?: throw new RuntimeException('GD could not create an image.');
        imagesavealpha($text, true);
        imagefill($text, 0, 0, (int) imagecolorallocatealpha($text, 0, 0, 0, 127));
        imagestring($text, 5, 2, 1, $mark, (int) imagecolorallocate($text, 20, 32, 28));
        $scale = max(2, intdiv(min($width, $height), 60));
        imagecopyresampled($image, $text, $width - imagesx($text) * $scale - (int) ($width * 0.05), $height - 18 * $scale - (int) ($height * 0.05), 0, 0, imagesx($text) * $scale, 18 * $scale, imagesx($text), 18);
        mt_srand();

        return $image;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 78);

        return (string) ob_get_clean();
    }

    private function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $name) ?: []));

        return Str::upper(Str::substr($words[0] ?? 'X', 0, 1).Str::substr($words[1] ?? '', 0, 1)) ?: 'X';
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    private function hsl(int $hue, float $saturation, float $lightness): array
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $lightness - $chroma / 2;
        [$r, $g, $b] = match (intdiv($hue, 60)) {
            0 => [$chroma, $x, 0],
            1 => [$x, $chroma, 0],
            2 => [0, $chroma, $x],
            3 => [0, $x, $chroma],
            4 => [$x, 0, $chroma],
            default => [$chroma, 0, $x],
        };

        $channel = fn (float $value): int => max(0, min(255, (int) round(($value + $m) * 255)));

        return [$channel($r), $channel($g), $channel($b)];
    }
}
