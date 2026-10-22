import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { fr } from '@/lib/i18n/fr';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editPassword } from '@/routes/security';

const items = [
    { title: fr.user.profile, href: editProfile() },
    { title: fr.user.password, href: editPassword() },
    { title: fr.user.appearance, href: editAppearance() },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="space-y-6 p-4 md:p-6">
            <PageHeader
                title={fr.settings.title}
                description={fr.settings.description}
            />

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-12">
                <nav
                    aria-label={fr.settings.title}
                    className="flex gap-1 overflow-x-auto lg:w-48 lg:flex-col"
                >
                    {items.map((item) => (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            className={cn(
                                'flex min-h-11 shrink-0 items-center rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground',
                                isCurrentOrParentUrl(item.href) &&
                                    'bg-accent text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>

                <section className="max-w-xl flex-1 space-y-12">
                    {children}
                </section>
            </div>
        </div>
    );
}
