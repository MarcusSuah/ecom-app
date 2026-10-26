import type { Auth, TenantSummary } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            tenant: TenantSummary | null;
            unreadNotificationsCount: number;
            unreadMessagesCount: number;
            cartCount: number;
            [key: string]: unknown;
        };
    }
}
