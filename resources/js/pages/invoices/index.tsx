import { Head, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { InvoicesNav } from '@/components/invoices/invoices-nav';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { periodRange } from '@/lib/period';
import { cn } from '@/lib/utils';
import { index as invoicesIndex, show as showInvoice } from '@/routes/invoices';
import type { InvoiceRow } from '@/types/invoices';
import type { Option } from '@/types/stock';

type Props = {
    rows: Paginated<InvoiceRow>;
    filters: {
        search: string;
        status: string | null;
        from: string | null;
        to: string | null;
    };
    statuses: Option[];
};

const t = fr.invoices;
const ALL = 'all';
const PERIODS = ['today', 'week', 'month'] as const;

const columns: Column<InvoiceRow>[] = [
    {
        key: 'number',
        header: t.columns.number,
        cell: (row) => (
            <span className="grid">
                <span
                    className={cn(row.status === 'annulee' && 'line-through')}
                >
                    {row.number}
                </span>
                <span className="text-xs font-normal text-muted-foreground tabular-nums">
                    {formatDate(row.issued_at)}
                </span>
            </span>
        ),
    },
    { key: 'client', header: t.columns.client, cell: (row) => row.client_name },
    {
        key: 'vehicle',
        header: t.columns.vehicle,
        cell: (row) => <Plate plate={row.plate} />,
    },
    {
        key: 'status',
        header: t.columns.status,
        cell: (row) => <StatusBadge type="invoice" status={row.status} />,
    },
    {
        key: 'total',
        header: t.columns.total,
        align: 'end',
        cell: (row) => <MoneyText value={row.total} />,
    },
    {
        key: 'remaining',
        header: t.columns.remaining,
        align: 'end',
        cell: (row) =>
            Number(row.remaining) > 0 ? (
                <MoneyText value={row.remaining} className="text-destructive" />
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
];

export default function InvoicesIndex({ rows, filters, statuses }: Props) {
    const visit = (params: Record<string, string | undefined>) =>
        router.get(
            invoicesIndex.url(),
            {
                search: filters.search || undefined,
                status: filters.status ?? undefined,
                from: filters.from ?? undefined,
                to: filters.to ?? undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const currentPeriod = PERIODS.find((period) => {
        const range = periodRange(period);

        return range.from === filters.from && range.to === filters.to;
    });

    const filterBar = (
        <div className="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
            <Select
                value={filters.status ?? ALL}
                onValueChange={(value) =>
                    visit({ status: value === ALL ? undefined : value })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-40"
                    aria-label={t.columns.status}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allStatuses}</SelectItem>
                    {statuses.map((status) => (
                        <SelectItem key={status.value} value={status.value}>
                            {status.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={currentPeriod ?? ALL}
                onValueChange={(value) => {
                    const range =
                        value === ALL
                            ? null
                            : periodRange(value as (typeof PERIODS)[number]);
                    visit({ from: range?.from, to: range?.to });
                }}
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-44"
                    aria-label={fr.serviceOrders.date}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>
                        {fr.serviceOrders.periods.all}
                    </SelectItem>
                    {PERIODS.map((period) => (
                        <SelectItem key={period} value={period}>
                            {fr.serviceOrders.periods[period]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <>
            <Head title={t.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader title={t.title} description={t.description} />
                <InvoicesNav current="invoices" />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showInvoice.url(row.id)}
                    searchPlaceholder={t.search}
                    filters={filterBar}
                    empty={
                        <EmptyState
                            icon={FileText}
                            title={t.emptyTitle}
                            description={t.emptyText}
                        />
                    }
                />
            </div>
        </>
    );
}
