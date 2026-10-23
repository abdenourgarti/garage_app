import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { show as showInvoice } from '@/routes/invoices';
import type { InvoiceRow } from '@/types/invoices';

const t = fr.invoices;

/** Factures d'un client : une carte par facture, reste à payer mis en avant. */
export function InvoiceList({
    rows,
    emptyTitle = t.emptyTitle,
}: {
    rows: InvoiceRow[];
    emptyTitle?: string;
}) {
    if (rows.length === 0) {
        return <EmptyState icon={FileText} title={emptyTitle} />;
    }

    return (
        <ol className="grid gap-2">
            {rows.map((row) => (
                <li key={row.id}>
                    <Link
                        href={showInvoice(row.id)}
                        className={cn(
                            'grid gap-1.5 rounded-xl border bg-card p-3 hover:border-primary/40 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-center sm:gap-4',
                            row.status === 'annulee' && 'opacity-60',
                        )}
                    >
                        <span className="grid">
                            <span
                                className={cn(
                                    'font-semibold tabular-nums',
                                    row.status === 'annulee' && 'line-through',
                                )}
                            >
                                {row.number}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatDate(row.issued_at)}
                            </span>
                        </span>
                        <span className="flex flex-wrap items-center gap-2">
                            <Plate plate={row.plate} />
                            <StatusBadge type="invoice" status={row.status} />
                        </span>
                        <span className="grid justify-items-start sm:justify-items-end">
                            <MoneyText value={row.total} />
                            {Number(row.remaining) > 0 && (
                                <span className="text-sm text-destructive tabular-nums">
                                    {t.remaining} :{' '}
                                    <MoneyText value={row.remaining} />
                                </span>
                            )}
                        </span>
                    </Link>
                </li>
            ))}
        </ol>
    );
}
