import { router, useForm, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { resizeImage } from '@/lib/resize-image';
import { destroy, update } from '@/routes/profile/photo';
import type { SharedData } from '@/types';

/**
 * Choose, change or remove your own profile photo. It is shrunk in the
 * browser first and saved as soon as it is picked.
 */
export function ProfilePhoto() {
    const { auth } = usePage<SharedData>().props;
    const input = useRef<HTMLInputElement>(null);
    const form = useForm<{ avatar: File | null }>({ avatar: null });

    if (!auth.user) {
        return null;
    }

    const pick = async (file: File | undefined) => {
        if (!file) {
            return;
        }

        const resized = await resizeImage(file);
        form.transform(() => ({ avatar: resized }));
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
            <UserAvatar
                name={auth.user.name}
                avatar={auth.user.avatar ?? null}
            />
            <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => input.current?.click()}
                    >
                        <Camera aria-hidden="true" />
                        {form.processing
                            ? 'Uploading…'
                            : auth.user.avatar
                              ? 'Change photo'
                              : 'Add a photo'}
                    </Button>
                    {auth.user.avatar && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={form.processing}
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
                    Any photo from your camera or gallery; it is cropped to a
                    square.
                </p>
                <InputError message={form.errors.avatar} />
                <input
                    ref={input}
                    type="file"
                    accept="image/*"
                    className="sr-only"
                    aria-label="Profile photo"
                    tabIndex={-1}
                    onChange={(event) => void pick(event.target.files?.[0])}
                />
            </div>
        </div>
    );
}
