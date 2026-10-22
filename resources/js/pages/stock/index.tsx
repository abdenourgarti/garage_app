import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Droplets, Package, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { ArticleFormSheet } from '@/components/stock/article-form-sheet';
import { OilFormSheet } from '@/components/stock/oil-form-sheet';
import { StockNav } from '@/components/stock/stock-nav';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showArticle } from '@/routes/articles';
import { index as stockIndex } from '@/routes/stock';
import type { StockOptions } from '@/types/stock';

type ArticleRow = {
    id: number;
    name: string;
    reference: string | null;
    brand: string | null;
    category: string;
    stock_qty: string;
    unit: string;
    sale_price: string;
    stock_status: 'ok' | 'bas' | 'rupture';
    is_oil: boolean;
    is_active: boolean;
};

type Props = StockOptions & {
    rows: Paginated<ArticleRow>;
    filters: {
        search: string;
        category: number | null;
        low: boolean;
        oils: boolean;
    };
    lowCount: number;
};

const t = fr.stock;
const ALL = 'all';

const columns: Column<ArticleRow>[] = [
    {
        key: 'name',
        header: t.columns.article,
        cell: (row) => (
            <span className="inline-flex items-center gap-2">
                {row.is_oil && (
                    <Droplets
                        className="size-4 shrink-0 text-highlight"
                        aria-hidden="true"
                    />
                )}
                <span className="grid">
                    <span>{row.name}</span>
                    {(row.reference || row.brand) && (
                        <span className="text-xs font-normal text-muted-foreground">
                            {[row.brand, row.reference]
                                .filter(Boolean)
                                .join(', ')}
                        </span>
                    )}
                </span>
                {!row.is_active && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                        {t.article.inactive}
                    </span>
                )}
            </span>
        ),
    },
    {
        key: 'category',
        header: t.columns.category,
        cell: (row) => row.category,
        hideOnMobile: true,
    },
    {
        key: 'stock',
        header: t.columns.stock,
        align: 'end',
        cell: (row) => (
            <span className="font-semibold tabular-nums">
                {formatQuantity(row.stock_qty, row.unit)}
            </span>
        ),
    },
    {
        key: 'status',
        header: t.columns.status,
        cell: (row) => <StatusBadge type="stock" status={row.stock_status} />,
    },
    {
        key: 'price',
        header: t.columns.salePrice,
        align: 'end',
        cell: (row) => <MoneyText value={row.sale_price} />,
        hideOnMobile: true,
    },
];

export default function StockIndex({
    rows,
    filters,
    lowCount,
    ...options
}: Props) {
    const [creating, setCreating] = useState<'article' | 'oil' | null>(null);
    const [openCount, setOpenCount] = useState(0);

    const visit = (
        params: Record<string, string | number | boolean | undefined>,
    ) =>
        router.get(
            stockIndex.url(),
            {
                search: filters.search || undefined,
                category: filters.category ?? undefined,
                low: filters.low ? 1 : undefined,
                oils: filters.oils ? 1 : undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const open = (kind: 'article' | 'oil') => {
        setOpenCount((count) => count + 1);
        setCreating(kind);
    };

    const filterBar = (
        <div className="flex flex-wrap gap-2">
            <Select
                value={filters.category ? String(filters.category) : ALL}
                onValueChange={(value) =>
                    visit({ category: value === ALL ? undefined : value })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-52"
                    aria-label={t.columns.category}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allCategories}</SelectItem>
                    {options.categories.map((category) => (
                        <SelectItem
                            key={category.id}
                            value={String(category.id)}
                        >
                            {category.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Toggle
                variant="outline"
                className="h-11 px-3"
                pressed={filters.low}
                onPressedChange={(pressed) =>
                    visit({ low: pressed ? 1 : undefined })
                }
            >
                <AlertTriangle />
                {t.lowOnly}
            </Toggle>
            <Toggle
                variant="outline"
                className="h-11 px-3"
                pressed={filters.oils}
                onPressedChange={(pressed) =>
                    visit({ oils: pressed ? 1 : undefined })
                }
            >
                <Droplets />
                {t.oilsOnly}
            </Toggle>
        </div>
    );

    return (
        <>
            <Head title={t.tabArticles} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={t.title}
                    description={t.description}
                    actions={
                        <>
                            <Button
                                className="h-11"
                                onClick={() => open('article')}
                            >
                                <Plus />
                                {t.newArticle}
                            </Button>
                            <Button
                                className="h-11"
                                variant="outline"
                                onClick={() => open('oil')}
                            >
                                <Plus />
                                {t.newOil}
                            </Button>
                        </>
                    }
                />
                <StockNav current="articles" />

                {lowCount > 0 && !filters.low && (
                    <button
                        type="button"
                        onClick={() => visit({ low: 1 })}
                        className="flex min-h-11 w-full items-center gap-2 rounded-xl border border-warning/30 bg-warning/10 px-4 text-start text-sm font-medium text-warning"
                    >
                        <AlertTriangle
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {t.lowCount(lowCount)}
                    </button>
                )}

                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showArticle.url(row.id)}
                    searchPlaceholder={t.search}
                    filters={filterBar}
                    empty={
                        <EmptyState
                            icon={Package}
                            title={t.emptyTitle}
                            description={t.emptyText}
                            action={
                                <Button
                                    className="h-11"
                                    onClick={() => open('article')}
                                >
                                    <Plus />
                                    {t.newArticle}
                                </Button>
                            }
                        />
                    }
                />
            </div>

            <ArticleFormSheet
                key={`article-${openCount}`}
                open={creating === 'article'}
                onOpenChange={(value) => setCreating(value ? 'article' : null)}
                {...options}
            />
            <OilFormSheet
                key={`oil-${openCount}`}
                open={creating === 'oil'}
                onOpenChange={(value) => setCreating(value ? 'oil' : null)}
                {...options}
            />
        </>
    );
}
