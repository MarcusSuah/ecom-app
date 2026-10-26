import { ImagePlus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { resizeImage } from '@/lib/resize-image';

/**
 * Optional photos as proof when reporting a problem: previews before
 * sending, shrunk in the browser so phone photos fit the upload limit.
 * The chosen files sit in a real file input named `name`, so a plain
 * Inertia <Form> sends them.
 */
export function ProofPhotosInput({
    name = 'photos[]',
    max,
    suggested,
    errors = [],
}: {
    name?: string;
    max: number;
    suggested: number;
    errors?: (string | undefined)[];
}) {
    const input = useRef<HTMLInputElement>(null);
    const picker = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [preparing, setPreparing] = useState(false);
    const previews = useMemo(
        () => files.map((file) => URL.createObjectURL(file)),
        [files],
    );

    useEffect(
        () => () => previews.forEach((url) => URL.revokeObjectURL(url)),
        [previews],
    );

    // Keep the form's file input in step with the previews.
    useEffect(() => {
        if (!input.current || typeof DataTransfer === 'undefined') {
            return;
        }

        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.current.files = transfer.files;
    }, [files]);

    const add = async (list: FileList | null) => {
        if (!list?.length) {
            return;
        }

        setPreparing(true);

        try {
            const resized = await Promise.all(
                Array.from(list)
                    .slice(0, max - files.length)
                    .map(resizeImage),
            );
            setFiles((current) => [...current, ...resized].slice(0, max));
        } finally {
            setPreparing(false);

            if (picker.current) {
                picker.current.value = '';
            }
        }
    };

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium">
                Photos{' '}
                <span className="font-normal text-muted-foreground">
                    (optional)
                </span>
            </p>
            <p className="text-xs text-muted-foreground">
                Add at least {suggested} photos if you can (the item, its
                packaging and the problem), up to {max}. They help our team
                decide quickly and are seen only by you, the seller and our
                team.
            </p>
            <ul className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                {previews.map((url, index) => (
                    <li key={url} className="relative">
                        <img
                            src={url}
                            alt={`Photo ${index + 1}`}
                            className="aspect-square w-full rounded-md border object-cover"
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="secondary"
                            className="absolute top-1 right-1 size-6"
                            aria-label={`Remove photo ${index + 1}`}
                            onClick={() =>
                                setFiles((current) =>
                                    current.filter((_, i) => i !== index),
                                )
                            }
                        >
                            <X className="size-3.5" />
                        </Button>
                    </li>
                ))}
                {files.length < max && (
                    <li>
                        <button
                            type="button"
                            onClick={() => picker.current?.click()}
                            disabled={preparing}
                            className="flex aspect-square w-full flex-col items-center justify-center gap-1 rounded-md border border-dashed text-xs text-muted-foreground hover:border-primary hover:text-foreground"
                        >
                            <ImagePlus className="size-5" aria-hidden="true" />
                            {preparing ? 'Preparing…' : 'Add photos'}
                        </button>
                    </li>
                )}
            </ul>
            {files.length > 0 && files.length < suggested && (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                    {files.length} of {suggested} suggested photos added.
                </p>
            )}
            <input
                ref={picker}
                type="file"
                accept="image/*"
                multiple
                className="sr-only"
                tabIndex={-1}
                aria-label="Add photos"
                onChange={(event) => void add(event.target.files)}
            />
            {/* What the form submits. */}
            <input
                ref={input}
                type="file"
                name={name}
                multiple
                className="hidden"
                tabIndex={-1}
                aria-hidden="true"
            />
            {errors.filter(Boolean).map((message) => (
                <InputError key={message} message={message} />
            ))}
        </div>
    );
}
