export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function formatLimit(limit: {
    is_switch: boolean;
    value: number | boolean | null;
}): string {
    if (limit.is_switch) {
        return limit.value ? 'Included' : 'Not included';
    }

    return limit.value === null ? 'Unlimited' : String(limit.value);
}
