'use client';

import { toast } from 'sonner';
import { CheckCircle2, XCircle, AlertTriangle, Info } from 'lucide-react';

import { Switch } from '@/components/ui/switch';

const variants = {
    success: {
        icon: <CheckCircle2 className="size-4" />,
        style: {
            background: '#f0fdf4',
            border: '1px solid #bbf7d0',
            color: '#166534',
        },
    },
    error: {
        icon: <XCircle className="size-4" />,
        style: {
            background: '#fef2f2',
            border: '1px solid #fecaca',
            color: '#991b1b',
        },
    },
    warning: {
        icon: <AlertTriangle className="size-4" />,
        style: {
            background: '#fffbeb',
            border: '1px solid #fde68a',
            color: '#92400e',
        },
    },
    info: {
        icon: <Info className="size-4" />,
        style: {
            background: '#eff6ff',
            border: '1px solid #bfdbfe',
            color: '#1e40af',
        },
    },
};

export const notify = {
    default: (message, options = {}) => toast(message, options),

    success: (message, options = {}) =>
        toast.success(message, { ...variants.success, ...options }),

    error: (message, options = {}) =>
        toast.error(message, { ...variants.error, ...options }),

    warning: (message, options = {}) =>
        toast.warning(message, { ...variants.warning, ...options }),

    info: (message, options = {}) =>
        toast.info(message, { ...variants.info, ...options }),

    promise: (promise, messages = {}) =>
        toast.promise(promise, {
            loading: messages.loading ?? 'Loading...',
            success: messages.success ?? 'Done.',
            error: messages.error ?? 'Something went wrong.',
            ...messages,
        }),
};

export default notify;
