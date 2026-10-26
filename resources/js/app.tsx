import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    // Branded with the current marketplace (the shared "name" prop), not the build-time app name.
    title: (title, page) => {
        const siteName =
            typeof page?.props?.name === 'string' ? page.props.name : appName;

        return title ? `${title} - ${siteName}` : siteName;
    },
    layout: (name) => {
        switch (true) {
            case name === 'marketplace-unavailable':
            case name === 'error':
                return null;
            case name === 'welcome':
            case name.startsWith('catalog/'):
            case name.startsWith('content/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
