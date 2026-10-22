import { Head, Link, router } from '@inertiajs/react';
import { Plus, ShoppingCart } from 'lucide-react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { StockNav } from '@/components/stock/stock-nav';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import {
    create as createPurchase,
    index as purchasesIndex,
    show as showPurchase,
} from '@/routes/purchases';
import type { Option } from '@/types/stock';

type PurchaseRow = {
    id: number;
    supplier_name: string;
    status: 'draft' | 'ordered' | 'received' | 'cancelled';
    purchased_at: string;
    supplier_invoice_ref: string | null;
    total: string;
    amount_paid: string;
    remaining: string;
    payment_status: 'payee' | 'partielle' | 'impayee' | null;
};

type Props = {
    rows: Paginated<PurchaseRow>;
    filters: { search: string; status: string | null; supplier: number | null };
    statuses: Option[];
    suppliers: { id: number; name: string }[];
};

const t = fr.purchases;
const ALL = 'all';

const columns: Column<PurchaseRow>[] = [
    {
        key: 'number',
        header: t.columns.date,
        cell: (row) => (
            <span className="grid">
                <span>{t.number(row.id)}</span>
                <span className="text-xs font-normal text-muted-foreground tabular-nums">
                    {formatDate(row.purchased_at)}
                </span>
            </span>
        ),
    },
    {
        key: 'supplier',
        header: t.columns.supplier,
        cell: (row) => row.supplier_name,
    },
    {
        key: 'status',
        header: t.columns.status,
        cell: (row) => <StatusBadge type="purchase" status={row.status} />,
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
            row.payment_status ? (
                <span className="inline-flex items-center justify-end gap-2">
                    <StatusBadge type="invoice" status={row.payment_status} />
                    <MoneyText value={row.remaining} />
                </span>
            ) : (
                '—'
            ),
    },
];

export default function PurchasesIndex({
    rows,
    filters,
    statuses,
    suppliers,
}: Props) {
    const visit = (params: Record<string, string | undefined>) =>
        router.get(
            purchasesIndex.url(),
            {
                search: filters.search || undefined,
                status: filters.status ?? undefined,
                supplier: filters.supplier ?? undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const filterBar = (
        <div className="flex flex-wrap gap-2">
            <Select
                value={filters.status ?? ALL}
                onValueChange={(value) =>
                    visit({ status: value === ALL ? undefined : value })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-44"
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
                value={filters.supplier ? String(filters.supplier) : ALL}
                onValueChange={(value) =>
                    visit({ supplier: value === ALL ? undefined : value })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-56"
                    aria-label={t.columns.supplier}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allSuppliers}</SelectItem>
                    {suppliers.map((supplier) => (
                        <SelectItem
                            key={supplier.id}
                            value={String(supplier.id)}
                        >
                            {supplier.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );

    const newButton = (
        <Button asChild className="h-11">
            <Link href={createPurchase()}>
                <Plus />
                {t.newPurchase}
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={t.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={fr.stock.title}
                    description={fr.stock.description}
                    actions={newButton}
                />
                <StockNav current="purchases" />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showPurchase.url(row.id)}
                    searchPlaceholder={t.search}
                    filters={filterBar}
                    empty={
                        <EmptyState
                            icon={ShoppingCart}
                            title={t.emptyTitle}
                            description={t.emptyText}
                            action={newButton}
                        />
                    }
                />
            </div>
        </>
    );
}
