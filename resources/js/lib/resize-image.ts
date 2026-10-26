/** Longest side of a photo sent to the server; ImageResizer makes the final sizes. */
const MAX_SIDE = 2000;

/**
 * Shrinks a photo in the browser before it is uploaded, so large phone
 * photos fit the upload limit, and turns formats the browser can show
 * (HEIC on iPhones, for one) into JPEG. Falls back to the original file
 * when the browser cannot read it.
 */
export async function resizeImage(file: File): Promise<File> {
    if (
        !file.type.startsWith('image/') ||
        typeof createImageBitmap !== 'function'
    ) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const scale = Math.min(
            1,
            MAX_SIDE / Math.max(bitmap.width, bitmap.height),
        );
        const isSmallJpeg = scale === 1 && file.type === 'image/jpeg';

        if (isSmallJpeg) {
            bitmap.close();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const context = canvas.getContext('2d');

        if (!context) {
            bitmap.close();

            return file;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.88),
        );

        if (!blob) {
            return file;
        }

        const name = file.name.replace(/\.[^.]+$/, '') || 'photo';

        return new File([blob], `${name}.jpg`, {
            type: 'image/jpeg',
            lastModified: file.lastModified,
        });
    } catch {
        return file;
    }
}
