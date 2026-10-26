<?php

namespace App\Actions\Media;

use App\Support\ImageResizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Applies one save of a photo gallery: removes the photos the seller took
 * out, stores the new uploads (a display copy up to 1200px and a 400px
 * square thumbnail on the public disk), and puts the featured photo first.
 * Position 0 is the featured photo everywhere.
 *
 * Gallery rows need path, thumbnail_path and position columns.
 */
class SyncGallery
{
    /**
     * @template TImage of Model
     * @template TOwner of Model
     *
     * @param  HasMany<TImage, TOwner>  $images
     * @param  list<UploadedFile>  $uploads
     * @param  list<int>  $removeIds
     * @param  string|null  $cover  "existing:{id}" or "new:{index into $uploads}"; null keeps the current first photo
     * @return Collection<int, TImage> the gallery in display order
     */
    public function handle(HasMany $images, string $folder, array $uploads, array $removeIds, ?string $cover, int $max): Collection
    {
        $disk = Storage::disk('public');

        $removed = $images->getQuery()->clone()->whereKey($removeIds)->get();
        $remaining = $images->getQuery()->clone()->count() - $removed->count();

        if ($remaining + count($uploads) > $max) {
            throw ValidationException::withMessages([
                'images' => "You can have up to {$max} photos. Remove ".($remaining + count($uploads) - $max).' to add these.',
            ]);
        }

        foreach ($removed as $image) {
            $disk->delete([$image->getAttribute('path'), $image->getAttribute('thumbnail_path')]);
            $image->delete();
        }

        $added = [];
        $next = (int) $images->getQuery()->clone()->max('position') + 1;

        foreach ($uploads as $index => $upload) {
            $name = Str::lower(Str::ulid()).'.jpg';
            $path = "{$folder}/{$name}";
            $thumbnailPath = "{$folder}/thumbs/{$name}";

            $disk->put($path, ImageResizer::fit($upload->getRealPath(), 1200));
            $disk->put($thumbnailPath, ImageResizer::square($upload->getRealPath(), 400));

            $added[$index] = $images->create(['path' => $path, 'thumbnail_path' => $thumbnailPath, 'position' => min(255, $next++)]);
        }

        $gallery = $images->getQuery()->clone()->orderBy('position')->orderBy('id')->get();
        $featured = $this->featured($cover, $gallery, $added);

        if ($featured) {
            $gallery = $gallery->sortBy(fn (Model $image) => $image->is($featured) ? -1 : 0)->values();
        }

        // Renumber 0..n so the featured photo is always position 0.
        foreach ($gallery->values() as $position => $image) {
            if ((int) $image->getAttribute('position') !== $position) {
                $image->forceFill(['position' => $position])->save();
            }
        }

        return new Collection($gallery->values()->all());
    }

    /**
     * @template TImage of Model
     *
     * @param  Collection<int, TImage>  $gallery
     * @param  array<int, TImage>  $added
     * @return TImage|null
     */
    private function featured(?string $cover, Collection $gallery, array $added): ?Model
    {
        if ($cover === null || ! preg_match('/^(existing|new):(\d+)$/', $cover, $match)) {
            return null;
        }

        return $match[1] === 'new'
            ? ($added[(int) $match[2]] ?? null)
            : $gallery->first(fn (Model $image) => (int) $image->getKey() === (int) $match[2]);
    }
}
