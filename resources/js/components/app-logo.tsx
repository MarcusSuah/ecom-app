import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedData } from '@/types';

/** The marketplace's logo from Settings (or the app icon) and its name. */
export default function AppLogo() {
    const { name, marketplace } = usePage<SharedData>().props;

    return (
        <>
            {marketplace?.logo_url ? (
                <img
                    src={marketplace.logo_url}
                    alt=""
                    className="size-8 shrink-0 rounded-md object-contain"
                />
            ) : (
                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <AppLogoIcon className="size-8 fill-current text-white dark:text-black" />
                </div>
            )}
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
