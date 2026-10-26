import { router } from '@inertiajs/react';
import type { Method, RequestPayload } from '@inertiajs/core';
import { toast } from 'sonner';

type Options = {
    method?: Method;
    data?: RequestPayload;
    loading: string;
    success: string;
    error?: string;
};

export function promiseAction(
    url: string,
    { method = 'post', data = {}, loading, success, error }: Options,
) {
    const promise = new Promise<void>((resolve, reject) => {
        const callbacks = {
            preserveScroll: true,

            onSuccess: () => {
                resolve();
            },

            onError: (errors: Record<string, string>) => {
                reject(errors);
            },
        };

        router.visit(url, { method, data, ...callbacks });
    });

    toast.promise(promise, {
        loading,

        success,

        error: (errors: Record<string, string>) =>
            errors?.user ?? error ?? 'Something went wrong.',

        classNames: {
            toast: 'border shadow-lg',

            loading:
                'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',

            success:
                'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',

            error: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
        },
    });

    return promise;
}
