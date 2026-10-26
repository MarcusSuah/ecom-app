import { ImagePlus, Star, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { resizeImage } from '@/lib/resize-image';
import { cn } from '@/lib/utils';

export type GalleryImage = { id: number; url: string; thumbnail_url: string };

/** What the form sends: new files, saved photos to drop and the featured one. */
export type GalleryValue = {
    files: File[];
    remove: number[];
    /** "existing:{id}" or "new:{index into files}"; null keeps the current first photo. */
    cover: string | null;
};

export const emptyGallery: GalleryValue = {
    files: [],
    remove: [],
    cover: null,
};

type Tile = {
    key: string;
    cover: string;
    src: string;
    isNew: boolean;
    onRemove: () => void;
};

/**
 * Pick product photos with a preview before saving, choose the featured one
 * (shown on cards; the rest appear on the detail page) and remove photos.
 * Nothing is uploaded until the form is saved.
 */
export function GalleryPicker({
    existing,
    value,
    onChange,
    max,
    errors = {},
}: {
    existing: GalleryImage[];
    value: GalleryValue;
    onChange: (value: GalleryValue) => void;
    max: number;
    errors?: Record<string, string | undefined>;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [preparing, setPreparing] = useState(false);
    const previews = useMemo(
        () => value.files.map((file) => URL.createObjectURL(file)),
        [value.files],
    );

    useEffect(
        () => () => previews.forEach((url) => URL.revokeObjectURL(url)),
        [previews],
    );

    const kept = existing.filter((image) => !value.remove.includes(image.id));
    const tiles: Tile[] = [
        ...kept.map((image) => ({
            key: `existing-${image.id}`,
            cover: `existing:${image.id}`,
            src: image.thumbnail_url,
            isNew: false,
            onRemove: () =>
                onChange({
                    ...value,
                    remove: [...value.remove, image.id],
                    cover:
                        value.cover === `existing:${image.id}`
                            ? null
                            : value.cover,
                }),
        })),
        ...value.files.map((file, index) => ({
            key: `new-${index}-${file.name}`,
            cover: `new:${index}`,
            src: previews[index],
            isNew: true,
            onRemove: () => {
                const files = value.files.filter((_, i) => i !== index);
                // New photos after this one move down a place; keep the featured choice pointing at the same photo.
                const match = value.cover?.match(/^new:(\d+)$/);
                const coverIndex = match ? Number(match[1]) : null;
                const cover =
                    coverIndex === null
                        ? value.cover
                        : coverIndex === index
                          ? null
                          : `new:${coverIndex > index ? coverIndex - 1 : coverIndex}`;
                onChange({ ...value, files, cover });
            },
        })),
    ];

    const featured = value.cover ?? tiles[0]?.cover ?? null;
    const room = max - tiles.length;
    const fileErrors = Object.entries(errors)
        .filter(
            ([key, message]) =>
                message && (key === 'images' || key.startsWith('images.')),
        )
        .map(([, message]) => message as string);

    const addFiles = async (list: FileList | null) => {
        if (!list?.length) {
            return;
        }

        const picked = Array.from(list).slice(0, Math.max(0, room));

        if (input.current) {
            input.current.value = '';
        }

        setPreparing(true);

        try {
            const resized = await Promise.all(picked.map(resizeImage));
            onChange({ ...value, files: [...value.files, ...resized] });
        } finally {
            setPreparing(false);
        }
    };

    return (
        <div className="space-y-3">
            <ul className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                {tiles.map((tile) => {
                    const isFeatured = tile.cover === featured;

                    return (
                        <li
                            key={tile.key}
                            className={cn(
                                'group relative overflow-hidden rounded-md border',
                                isFeatured &&
                                    'ring-2 ring-primary ring-offset-2 ring-offset-background',
                            )}
                        >
                            <img
                                src={tile.src}
                                alt=""
                                className="aspect-square w-full object-cover"
                            />
                            {isFeatured && (
                                <span className="absolute bottom-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[11px] font-medium text-primary-foreground">
                                    Featured
                                </span>
                            )}
                            {tile.isNew && !isFeatured && (
                                <span className="absolute bottom-1 left-1 rounded bg-background/90 px-1.5 py-0.5 text-[11px] font-medium">
                                    New
                                </span>
                            )}
                            <div className="absolute top-1 right-1 flex gap-1">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="secondary"
                                    className="size-7"
                                    aria-label={
                                        isFeatured
                                            ? 'Featured photo'
                                            : 'Use as featured photo'
                                    }
                                    aria-pressed={isFeatured}
                                    title={
                                        isFeatured
                                            ? 'Featured photo'
                                            : 'Use as featured photo'
                                    }
                                    onClick={() =>
                                        onChange({
                                            ...value,
                                            cover: tile.cover,
                                        })
                                    }
                                >
                                    <Star
                                        className={cn(
                                            isFeatured &&
                                                'fill-current text-amber-500',
                                        )}
                                    />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="secondary"
                                    className="size-7"
                                    aria-label="Remove photo"
                                    title="Remove photo"
                                    onClick={tile.onRemove}
                                >
                                    <X />
                                </Button>
                            </div>
                        </li>
                    );
                })}
                {room > 0 && (
                    <li>
                        <button
                            type="button"
                            onClick={() => input.current?.click()}
                            disabled={preparing}
                            className="flex aspect-square w-full flex-col items-center justify-center gap-1 rounded-md border border-dashed text-sm text-muted-foreground hover:border-primary hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <ImagePlus className="size-6" aria-hidden="true" />
                            {preparing ? 'Preparing…' : 'Add photos'}
                        </button>
                    </li>
                )}
            </ul>
            <input
                ref={input}
                type="file"
                multiple
                accept="image/*"
                className="sr-only"
                aria-label="Add photos"
                tabIndex={-1}
                onChange={(event) => addFiles(event.target.files)}
            />
            <p className="text-xs text-muted-foreground">
                Up to {max} photos from your camera or gallery, any size; they
                are resized to fit. Tap the star to choose the featured photo
                shown on product cards; the others appear on the product page.
                Changes are saved with the form.
            </p>
            {fileErrors.map((message) => (
                <InputError key={message} message={message} />
            ))}
        </div>
    );
}
