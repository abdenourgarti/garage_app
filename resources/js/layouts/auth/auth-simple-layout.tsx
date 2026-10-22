import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background p-4 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4 text-center">
                        <div className="flex items-center gap-3">
                            <span className="flex size-12 items-center justify-center rounded-xl bg-sidebar text-sidebar-primary">
                                <AppLogoIcon className="size-6" />
                            </span>
                            <span className="text-2xl font-semibold">
                                {name}
                            </span>
                        </div>

                        <div className="space-y-1">
                            <h1 className="text-xl font-semibold">{title}</h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    <div className="rounded-xl border bg-card p-6 shadow-xs">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
