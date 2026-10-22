import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { edit as editGarage } from '@/routes/admin/garage';
import { index as references } from '@/routes/admin/references';

export default function AdminLayout({ children }: PropsWithChildren) {
    const { can } = usePage().props.auth;
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const items = [
        ...(can.admin
            ? [
                  {
                      title: fr.admin.garage,
                      href: editGarage(),
                      match: editGarage(),
                  },
              ]
            : []),
        {
            title: fr.admin.references,
            href: references({ type: 'brands' }),
            match: '/admin/references',
        },
    ];

    return (
        <div className="space-y-6 p-4 md:p-6">
            <PageHeader
                title={fr.admin.title}
                description={fr.admin.description}
            />

            {items.length > 1 && (
                <nav
                    aria-label={fr.admin.title}
                    className="flex gap-1 border-b"
                >
                    {items.map((item) => (
                        <Link
                            key={item.title}
                            href={item.href}
                            className={cn(
                                '-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium text-muted-foreground hover:text-foreground',
                                isCurrentOrParentUrl(item.match) &&
                                    'border-primary text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>
            )}

            {children}
        </div>
    );
}
