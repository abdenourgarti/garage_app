import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center rounded-md bg-sidebar-accent text-sidebar-primary">
                <AppLogoIcon className="size-5" />
            </div>
            <span className="truncate text-base font-semibold">{name}</span>
        </>
    );
}
