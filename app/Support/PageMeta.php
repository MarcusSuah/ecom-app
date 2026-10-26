<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Search and link-preview tags for a page, passed as the "meta" prop. The
 * root view prints them for crawlers when SSR is off; <Seo> keeps them in
 * step on client-side visits.
 */
final class PageMeta
{
    /**
     * @return array{title: string|null, description: string, image: string|null, url: string, type: string}
     */
    public static function make(?string $title, string $description, ?string $image = null, string $type = 'website'): array
    {
        return [
            'title' => $title,
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? ''), 160),
            'image' => $image !== null ? url($image) : null,
            'url' => url()->current(),
            'type' => $type,
        ];
    }
}
