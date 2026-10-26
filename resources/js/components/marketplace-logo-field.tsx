import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { destroy, update } from '@/routes/admin/settings/logo';

/**
 * Upload or remove the marketplace logo shown in the storefront header and
 * footer and in the dashboard. Saved as soon as a file is picked.
 */
export function MarketplaceLogoField({ logoUrl }: { logoUrl: string | null }) {
    const input = useRef<HTMLInputElement>(null);
    const form = useForm<{ logo: File | null }>({ logo: null });

    const pick = (file: File | undefined) => {
        if (!file) {
            return;
        }

        form.transform(() => ({ logo: file }));
        form.post(update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (input.current) {
                    input.current.value = '';
                }
            },
        });
    };

    return (
        <div className="flex flex-wrap items-center gap-4">
            <div className="flex h-16 w-40 items-center justify-center overflow-hidden rounded-md border bg-muted/40">
                {logoUrl ? (
                    <img
                        src={logoUrl}
                        alt="Marketplace logo"
                        className="max-h-full max-w-full object-contain"
                    />
                ) : (
                    <span className="text-xs text-muted-foreground">
                        No logo yet
                    </span>
                )}
            </div>
            <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => input.current?.click()}
                    >
                        <ImagePlus aria-hidden="true" />
                        {form.processing
                            ? 'Uploading…'
                            : logoUrl
                              ? 'Change logo'
                              : 'Upload logo'}
                    </Button>
                    {logoUrl && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                router.delete(destroy.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 aria-hidden="true" />
                            Remove
                        </Button>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    PNG with a transparent background works best. JPG, PNG or
                    WebP, up to 5 MB.
                </p>
                <InputError message={form.errors.logo} />
                <input
                    ref={input}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="sr-only"
                    aria-label="Marketplace logo"
                    tabIndex={-1}
                    onChange={(event) => pick(event.target.files?.[0])}
                />
            </div>
        </div>
    );
}
