<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Photo gallery fields sent with a product form: new photos, photos to
 * remove and which one is featured ("existing:{id}" or "new:{index}").
 * Photos of any size are accepted; ImageResizer brings them to the
 * gallery format.
 */
trait ValidatesGallery
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function galleryRules(int $max): array
    {
        return [
            'images' => ['nullable', 'array', "max:{$max}"],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'cover' => ['nullable', 'string', 'regex:/^(existing|new):\d+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function galleryMessages(): array
    {
        return [
            'images.max' => 'Too many photos at once.',
            'images.*.image' => 'Each file must be a photo.',
            'images.*.mimes' => 'Photos must be JPG, PNG or WebP.',
            'images.*.max' => 'Each photo must be 10 MB or smaller.',
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function galleryUploads(): array
    {
        return array_values(Arr::wrap($this->file('images')));
    }

    /**
     * @return list<int>
     */
    public function galleryRemovals(): array
    {
        return array_values(array_map('intval', Arr::wrap($this->input('remove_images', []))));
    }

    public function galleryCover(): ?string
    {
        return $this->filled('cover') ? $this->string('cover')->toString() : null;
    }
}
