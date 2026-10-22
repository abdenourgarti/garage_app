import { Link } from '@inertiajs/react';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { index as clientsIndex } from '@/routes/clients';
import { index as vehiclesIndex } from '@/routes/vehicles';

/** Bascule Clients / Véhicules en tête des deux listes. */
export function ClientsNav({ current }: { current: 'clients' | 'vehicles' }) {
    const items = [
        { key: 'clients', title: fr.clients.tabClients, href: clientsIndex() },
        {
            key: 'vehicles',
            title: fr.clients.tabVehicles,
            href: vehiclesIndex(),
        },
    ] as const;

    return (
        <nav aria-label={fr.clients.title} className="flex gap-1 border-b">
            {items.map((item) => (
                <Link
                    key={item.key}
                    href={item.href}
                    className={cn(
                        '-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium text-muted-foreground hover:text-foreground',
                        current === item.key &&
                            'border-primary text-foreground',
                    )}
                >
                    {item.title}
                </Link>
            ))}
        </nav>
    );
}
