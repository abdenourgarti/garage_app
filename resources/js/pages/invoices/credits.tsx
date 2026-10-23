import { Head } from '@inertiajs/react';
import { HandCoins, Phone } from 'lucide-react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { InvoicesNav } from '@/components/invoices/invoices-nav';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showClient } from '@/routes/clients';

type CreditRow = {
    id: number;
    full_name: string;
    phone: string;
    credit: string;
    open_invoices: number;
    oldest: string;
    days: number;
};

const t = fr.invoices.credits;

const columns: Column<CreditRow>[] = [
    {
        key: 'client',
        header: t.columns.client,
        cell: (row) => row.full_name,
    },
    {
        key: 'phone',
        header: t.columns.phone,
        cell: (row) => (
            <a
                href={`tel:${row.phone.replace(/[^\d+]/g, '')}`}
                onClick={(event) => event.stopPropagation()}
                className="relative z-10 inline-flex min-h-11 items-center gap-1.5 font-medium text-primary tabular-nums md:min-h-0"
                aria-label={`${fr.clients.call} ${row.phone}`}
            >
                <Phone className="size-4" />
                {row.phone}
            </a>
        ),
    },
    {
        key: 'invoices',
        header: t.columns.invoices,
        align: 'end',
        cell: (row) => (
            <span className="tabular-nums">{row.open_invoices}</span>
        ),
    },
    {
        key: 'since',
        header: t.columns.since,
        cell: (row) => (
            <span className="grid">
                <span className="tabular-nums">{formatDate(row.oldest)}</span>
                <span className="text-xs text-muted-foreground">
                    {t.days(row.days)}
                </span>
            </span>
        ),
    },
    {
        key: 'credit',
        header: t.columns.credit,
        align: 'end',
        cell: (row) => (
            <MoneyText value={row.credit} className="text-destructive" />
        ),
    },
];

export default function Credits({
    rows,
    total,
}: {
    rows: Paginated<CreditRow>;
    filters: { search: string };
    total: string;
}) {
    return (
        <>
            <Head title={fr.invoices.tabCredits} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={fr.invoices.title}
                    description={fr.invoices.description}
                    actions={
                        <div className="rounded-xl border bg-card px-4 py-2 text-right">
                            <p className="text-xs text-muted-foreground">
                                {t.totalLabel}
                            </p>
                            <MoneyText
                                value={total}
                                className="text-xl text-destructive"
                            />
                        </div>
                    }
                />
                <InvoicesNav current="credits" />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showClient.url(row.id)}
                    searchPlaceholder={t.search}
                    empty={
                        <EmptyState
                            icon={HandCoins}
                            title={t.emptyTitle}
                            description={t.emptyText}
                        />
                    }
                />
            </div>
        </>
    );
}
