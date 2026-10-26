import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    Download,
    ExternalLink,
    Minus,
    Pencil,
    Plus,
    Scale,
    Trash2,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { PeriodPicker } from '@/components/app/period-picker';
import { StatCard } from '@/components/app/stat-card';
import { EntrySheet } from '@/components/cash/entry-sheet';
import type { ManualEntry } from '@/components/cash/entry-sheet';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatMoney } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import type { Period, PeriodPreset } from '@/lib/period';
import { exportMethod, index as cashIndex } from '@/routes/cash';
import expenses from '@/routes/cash/expenses';
import incomes from '@/routes/cash/incomes';
import { show as showEmployee } from '@/routes/employees';
import { show as showInvoice } from '@/routes/invoices';
import { show as showPurchase } from '@/routes/purchases';
import { show as showOrder } from '@/routes/service-orders';

type Movement = {
    kind: 'income' | 'expense';
    origin:
        | 'payment'
        | 'other_income'
        | 'manual'
        | 'advance'
        | 'purchase_payment'
        | 'salary_payment'
        | 'service_order';
    id: number;
    day: string;
    label: string;
    category: string;
    method: string | null;
    amount: string;
    link_id: number | null;
    link_extra: string | null;
    editable: boolean;
    notes: string | null;
    category_id: number | null;
    method_id: number | null;
};

type Total = { name: string; total: string };

type Props = {
    period: { from: string; to: string; preset: string | null };
    filters: {
        kind: string | null;
        category: number | null;
        method: number | null;
    };
    summary: {
        incomes: string;
        expenses: string;
        profit: string;
        by_category: (Total & { id: number })[];
        by_method: Total[];
    };
    cashToday: string;
    rows: Paginated<Movement>;
    categories: { id: number; name: string; manual: boolean }[];
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.cash;
const ALL = 'all';

/** Lien vers ce qui a créé l'écriture (facture, achat, fiche employé, prestation). */
function sourceHref(row: Movement): string | null {
    if (row.link_id === null) {
        return null;
    }

    switch (row.origin) {
        case 'payment':
            return showInvoice.url(row.link_id);
        case 'purchase_payment':
            return showPurchase.url(row.link_id);
        case 'service_order':
            return showOrder.url(row.link_id);
        case 'salary_payment':
        case 'advance':
            return showEmployee.url(row.link_id, {
                query: { period: row.link_extra ?? undefined },
            });
        default:
            return null;
    }
}

/** Barres horizontales simples : la plus grande valeur remplit la ligne. */
function Breakdown({
    title,
    rows,
    onPick,
}: {
    title: string;
    rows: Total[];
    onPick?: (index: number) => void;
}) {
    const max = Math.max(...rows.map((row) => Number(row.total)), 1);

    return (
        <section className="rounded-xl border bg-card p-4">
            <h2 className="mb-3 font-semibold">{title}</h2>
            {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t.nothing}</p>
            ) : (
                <ul className="grid gap-2">
                    {rows.map((row, index) => (
                        <li key={row.name}>
                            <button
                                type="button"
                                disabled={!onPick}
                                onClick={() => onPick?.(index)}
                                className="grid w-full gap-1 rounded-md p-1 text-start enabled:hover:bg-accent/50"
                            >
                                <span className="flex items-baseline justify-between gap-3 text-sm">
                                    <span className="truncate">{row.name}</span>
                                    <MoneyText value={row.total} />
                                </span>
                                <span className="h-2 overflow-hidden rounded-full bg-muted">
                                    <span
                                        className="block h-full rounded-full bg-primary/70"
                                        style={{
                                            width: `${(Number(row.total) / max) * 100}%`,
                                        }}
                                    />
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function CashIndex({
    period,
    filters,
    summary,
    cashToday,
    rows,
    categories,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const [sheet, setSheet] = useState<{
        kind: 'expense' | 'income';
        entry: ManualEntry | null;
    } | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [sheetCount, setSheetCount] = useState(0);
    const [deleting, setDeleting] = useState<Movement | null>(null);

    const query = {
        from: period.from,
        to: period.to,
        preset: period.preset ?? undefined,
        kind: filters.kind ?? undefined,
        category: filters.category ? String(filters.category) : undefined,
        method: filters.method ? String(filters.method) : undefined,
    };

    const visit = (params: Record<string, string | undefined>) =>
        router.get(
            cashIndex.url(),
            { ...query, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const value: Period = {
        preset: (period.preset as PeriodPreset | null) ?? 'custom',
        from: period.from,
        to: period.to,
    };

    const open = (
        kind: 'expense' | 'income',
        entry: ManualEntry | null = null,
    ) => {
        setSheetCount((count) => count + 1);
        setSheet({ kind, entry });
        setSheetOpen(true);
    };

    const columns: Column<Movement>[] = [
        {
            key: 'label',
            header: t.columns.label,
            cell: (row) => (
                <span className="grid">
                    <span className="flex items-center gap-1.5">
                        {row.kind === 'income' ? (
                            <ArrowDownLeft
                                className="size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                        ) : (
                            <ArrowUpRight
                                className="size-4 shrink-0 text-destructive"
                                aria-hidden="true"
                            />
                        )}
                        {row.label}
                    </span>
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        {formatDate(row.day)}
                    </span>
                </span>
            ),
        },
        {
            key: 'category',
            header: t.columns.category,
            cell: (row) => row.category,
        },
        {
            key: 'method',
            header: t.columns.method,
            cell: (row) => row.method ?? '—',
        },
        {
            key: 'amount',
            header: t.columns.amount,
            align: 'end',
            cell: (row) => (
                <MoneyText
                    value={
                        row.kind === 'expense'
                            ? -Number(row.amount)
                            : Number(row.amount)
                    }
                    colorBySign
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'end',
            cell: (row) => {
                const href = sourceHref(row);

                if (row.editable) {
                    return (
                        <span className="inline-flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon-lg"
                                aria-label={`${t.edit} ${row.label}`}
                                onClick={() =>
                                    open(row.kind, {
                                        id: row.id,
                                        label: row.label,
                                        amount: row.amount,
                                        day: row.day,
                                        method_id: row.method_id,
                                        category_id: row.category_id,
                                        notes: row.notes,
                                    })
                                }
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-lg"
                                className="text-destructive hover:text-destructive"
                                aria-label={`${t.delete} ${row.label}`}
                                onClick={() => setDeleting(row)}
                            >
                                <Trash2 />
                            </Button>
                        </span>
                    );
                }

                return href ? (
                    <Button asChild variant="ghost" size="icon-lg">
                        <Link
                            href={href}
                            aria-label={`${t.source} : ${row.label}`}
                        >
                            <ExternalLink />
                        </Link>
                    </Button>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {t.auto}
                    </span>
                );
            },
        },
    ];

    const filterBar = (
        <div className="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
            <Select
                value={filters.kind ?? ALL}
                onValueChange={(kind) =>
                    visit({
                        kind: kind === ALL ? undefined : kind,
                        page: undefined,
                    })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-48"
                    aria-label={t.allKinds}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allKinds}</SelectItem>
                    <SelectItem value="income">{t.kinds.income}</SelectItem>
                    <SelectItem value="expense">{t.kinds.expense}</SelectItem>
                </SelectContent>
            </Select>
            <Select
                value={filters.category ? String(filters.category) : ALL}
                onValueChange={(category) =>
                    visit({
                        category: category === ALL ? undefined : category,
                        page: undefined,
                    })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-56"
                    aria-label={t.columns.category}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allCategories}</SelectItem>
                    {categories.map((category) => (
                        <SelectItem
                            key={category.id}
                            value={String(category.id)}
                        >
                            {category.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={filters.method ? String(filters.method) : ALL}
                onValueChange={(method) =>
                    visit({
                        method: method === ALL ? undefined : method,
                        page: undefined,
                    })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-44"
                    aria-label={t.columns.method}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allMethods}</SelectItem>
                    {paymentMethods.map((method) => (
                        <SelectItem key={method.id} value={String(method.id)}>
                            {method.name}
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
                <PageHeader
                    title={t.title}
                    description={t.description}
                    actions={
                        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                            <Button
                                className="h-11"
                                onClick={() => open('expense')}
                            >
                                <Minus />
                                {t.addExpense}
                            </Button>
                            <Button
                                className="h-11"
                                variant="outline"
                                onClick={() => open('income')}
                            >
                                <Plus />
                                {t.addIncome}
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="col-span-2 h-11"
                            >
                                <a href={exportMethod.url({ query })}>
                                    <Download />
                                    <span className="sm:hidden">
                                        {t.exportShort}
                                    </span>
                                    <span className="hidden sm:inline">
                                        {t.export}
                                    </span>
                                </a>
                            </Button>
                        </div>
                    }
                />

                <PeriodPicker
                    value={value}
                    onChange={(next) => {
                        if (next.from && next.to) {
                            visit({
                                from: next.from,
                                to: next.to,
                                preset: next.preset,
                                page: undefined,
                            });
                        }
                    }}
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label={t.incomes}
                        value={Number(summary.incomes)}
                        kind="money"
                        icon={ArrowDownLeft}
                    />
                    <StatCard
                        label={t.expenses}
                        value={Number(summary.expenses)}
                        kind="money"
                        icon={ArrowUpRight}
                    />
                    <StatCard
                        label={t.profit}
                        value={Number(summary.profit)}
                        kind="money"
                        icon={Scale}
                        colorBySign
                    />
                    <StatCard
                        label={t.cashToday}
                        value={Number(cashToday)}
                        kind="money"
                        icon={Banknote}
                        colorBySign
                        hint={
                            <span className="text-xs">{t.cashTodayHint}</span>
                        }
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Breakdown
                        title={t.byCategory}
                        rows={summary.by_category}
                        onPick={(index) =>
                            visit({
                                category: String(summary.by_category[index].id),
                                kind: undefined,
                                page: undefined,
                            })
                        }
                    />
                    <Breakdown title={t.byMethod} rows={summary.by_method} />
                </div>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t.movements}</h2>
                    <DataTable
                        key={JSON.stringify(query)}
                        rows={rows}
                        columns={columns}
                        rowKey={(row) => `${row.origin}-${row.id}`}
                        filters={filterBar}
                        empty={
                            <EmptyState
                                icon={Wallet}
                                title={t.emptyTitle}
                                description={t.emptyText}
                            />
                        }
                    />
                </section>
            </div>

            {sheet && (
                <EntrySheet
                    key={sheetCount}
                    kind={sheet.kind}
                    entry={sheet.entry}
                    open={sheetOpen}
                    onOpenChange={setSheetOpen}
                    categories={categories.filter(
                        (category) =>
                            category.manual ||
                            category.id === sheet.entry?.category_id,
                    )}
                    paymentMethods={paymentMethods}
                    defaultPaymentMethodId={defaultPaymentMethodId}
                />
            )}
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(isOpen) => !isOpen && setDeleting(null)}
                title={t.deleteTitle}
                description={
                    deleting
                        ? `${deleting.label} · ${formatMoney(deleting.amount)}`
                        : t.deleteText
                }
                confirmLabel={t.delete}
                destructive
                onConfirm={() =>
                    deleting &&
                    router.delete(
                        deleting.kind === 'expense'
                            ? expenses.destroy.url(deleting.id)
                            : incomes.destroy.url(deleting.id),
                        {
                            preserveScroll: true,
                            onFinish: () => setDeleting(null),
                        },
                    )
                }
            />
        </>
    );
}
