import { Link } from '@inertiajs/react';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { credits, index as invoicesIndex } from '@/routes/invoices';

/** Bascule Factures / Crédits clients en tête des deux listes. */
export function InvoicesNav({ current }: { current: 'invoices' | 'credits' }) {
    const items = [
        {
            key: 'invoices',
            title: fr.invoices.tabInvoices,
            href: invoicesIndex(),
        },
        { key: 'credits', title: fr.invoices.tabCredits, href: credits() },
    ] as const;

    return (
        <nav aria-label={fr.invoices.title} className="flex gap-1 border-b">
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
