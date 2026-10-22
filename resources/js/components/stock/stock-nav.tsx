import { Link } from '@inertiajs/react';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { index as purchasesIndex } from '@/routes/purchases';
import { index as stockIndex } from '@/routes/stock';
import { index as suppliersIndex } from '@/routes/suppliers';

/** Onglets Articles · Achats · Fournisseurs du module Stock. */
export function StockNav({
    current,
}: {
    current: 'articles' | 'purchases' | 'suppliers';
}) {
    const items = [
        { key: 'articles', title: fr.stock.tabArticles, href: stockIndex() },
        {
            key: 'purchases',
            title: fr.stock.tabPurchases,
            href: purchasesIndex(),
        },
        {
            key: 'suppliers',
            title: fr.stock.tabSuppliers,
            href: suppliersIndex(),
        },
    ] as const;

    return (
        <nav
            aria-label={fr.stock.title}
            className="flex gap-1 overflow-x-auto border-b"
        >
            {items.map((item) => (
                <Link
                    key={item.key}
                    href={item.href}
                    className={cn(
                        '-mb-px flex min-h-11 shrink-0 items-center border-b-2 border-transparent px-3 text-sm font-medium text-muted-foreground hover:text-foreground',
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
