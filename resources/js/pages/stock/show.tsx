import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Droplets, Pencil, Power, Tags } from 'lucide-react';
import { useState } from 'react';
import ArticleController from '@/actions/App/Http/Controllers/Stock/ArticleController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import { AdjustStockSheet } from '@/components/stock/adjust-stock-sheet';
import { ArticleFormSheet } from '@/components/stock/article-form-sheet';
import { OilFormSheet } from '@/components/stock/oil-form-sheet';
import { StockNav } from '@/components/stock/stock-nav';
import { Button } from '@/components/ui/button';
import { formatDate, formatQuantity, formatTime } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { show as showArticle } from '@/routes/articles';
import { show as showPurchase } from '@/routes/purchases';
import type { OilSheet, StockOptions } from '@/types/stock';

type Movement = {
    id: number;
    created_at: string;
    type: string;
    quantity: string;
    unit_cost: string | null;
    reason: string | null;
    user: string | null;
    purchase_id: number | null;
};

type Props = StockOptions & {
    article: {
        id: number;
        name: string;
        brand: string | null;
        reference: string | null;
        article_category_id: number;
        category: string;
        stock_unit: string;
        unit: string;
        purchase_price: string;
        sale_price: string;
        stock_qty: string;
        alert_threshold: string | null;
        volume_l: string | null;
        stock_status: 'ok' | 'bas' | 'rupture';
        margin: string;
        oil_format: string | null;
        format_label: string | null;
        can_open: boolean;
        is_active: boolean;
    };
    oil: OilSheet | null;
    movements: Paginated<Movement>;
};

const t = fr.stock.article;

export default function ArticleShow({
    article,
    oil,
    movements,
    ...options
}: Props) {
    const [sheet, setSheet] = useState<'edit' | 'oil' | 'adjust' | null>(null);
    const [confirm, setConfirm] = useState<'open' | 'toggle' | null>(null);
    const [openCount, setOpenCount] = useState(0);
    const isCan = article.oil_format !== null && article.oil_format !== 'bulk';

    const open = (value: 'edit' | 'oil' | 'adjust') => {
        setOpenCount((count) => count + 1);
        setSheet(value);
    };

    const post = (url: string, method: 'post' | 'patch') =>
        router[method](
            url,
            {},
            { preserveScroll: true, onFinish: () => setConfirm(null) },
        );

    const columns: Column<Movement>[] = [
        {
            key: 'date',
            header: t.date,
            cell: (row) => (
                <span className="tabular-nums">
                    {formatDate(row.created_at)}{' '}
                    <span className="text-muted-foreground">
                        {formatTime(row.created_at)}
                    </span>
                </span>
            ),
        },
        { key: 'type', header: t.type, cell: (row) => row.type },
        {
            key: 'quantity',
            header: t.quantity,
            align: 'end',
            cell: (row) => (
                <span
                    className={cn(
                        'font-semibold tabular-nums',
                        Number(row.quantity) > 0
                            ? 'text-success'
                            : 'text-destructive',
                    )}
                >
                    {Number(row.quantity) > 0 ? '+' : ''}
                    {formatQuantity(row.quantity, article.unit)}
                </span>
            ),
        },
        {
            key: 'reason',
            header: t.reasonOrSource,
            cell: (row) =>
                row.purchase_id ? (
                    <Link
                        href={showPurchase(row.purchase_id)}
                        className="text-primary hover:underline"
                    >
                        {t.purchaseLink(row.purchase_id)}
                    </Link>
                ) : (
                    (row.reason ?? '—')
                ),
        },
        {
            key: 'user',
            header: t.user,
            cell: (row) => row.user ?? '—',
            hideOnMobile: true,
        },
    ];

    return (
        <>
            <Head title={article.name} />
            <div className="space-y-6 p-4 md:p-6">
                <StockNav current="articles" />

                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0 space-y-2">
                        <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
                            {oil && (
                                <Droplets
                                    className="size-6 text-highlight"
                                    aria-hidden="true"
                                />
                            )}
                            {article.name}
                            {!article.is_active && (
                                <span className="rounded-full bg-muted px-2.5 py-0.5 text-sm font-normal text-muted-foreground">
                                    {t.inactive}
                                </span>
                            )}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {[
                                article.category,
                                article.brand,
                                article.reference,
                                oil?.usage_label,
                                oil?.approval,
                            ]
                                .filter(Boolean)
                                .join(', ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                        <Button className="h-11" onClick={() => open('adjust')}>
                            <ClipboardCheck />
                            {t.adjust}
                        </Button>
                        {isCan && (
                            <Button
                                className="h-11"
                                variant="highlight"
                                onClick={() => setConfirm('open')}
                                disabled={!article.can_open}
                                title={
                                    article.can_open
                                        ? undefined
                                        : t.openCanNoBulk
                                }
                            >
                                <Droplets />
                                {t.openCan}
                            </Button>
                        )}
                        <Button
                            className="h-11"
                            variant="outline"
                            onClick={() => open('edit')}
                        >
                            <Pencil />
                            {fr.clients.edit}
                        </Button>
                        <Button
                            className="h-11"
                            variant="outline"
                            onClick={() => setConfirm('toggle')}
                        >
                            <Power />
                            {article.is_active ? t.deactivate : t.reactivate}
                        </Button>
                    </div>
                </div>
                {isCan && !article.can_open && (
                    <p className="text-sm text-muted-foreground">
                        {t.openCanNoBulk}
                    </p>
                )}

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-sm text-muted-foreground">
                            {t.stock}
                        </p>
                        <p className="mt-1 text-3xl font-semibold tabular-nums">
                            {formatQuantity(article.stock_qty, article.unit)}
                        </p>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <StatusBadge
                                type="stock"
                                status={article.stock_status}
                            />
                            {article.alert_threshold && (
                                <span>
                                    {fr.stock.fields.alertThreshold} :{' '}
                                    {formatQuantity(
                                        article.alert_threshold,
                                        article.unit,
                                    )}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-sm text-muted-foreground">
                            {fr.stock.fields.salePrice}
                        </p>
                        <MoneyText
                            value={article.sale_price}
                            className="mt-1 block text-2xl"
                        />
                        <p className="mt-2 text-sm text-muted-foreground">
                            {fr.stock.fields.purchasePrice} :{' '}
                            <MoneyText
                                value={article.purchase_price}
                                className="font-medium"
                            />
                        </p>
                    </div>
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-sm text-muted-foreground">
                            {t.margin}
                        </p>
                        <MoneyText
                            value={article.margin}
                            colorBySign
                            className="mt-1 block text-2xl"
                        />
                        <p className="mt-2 text-sm text-muted-foreground">
                            {article.oil_format === 'bulk'
                                ? fr.stock.oil.perLitre
                                : (article.format_label ?? '')}
                        </p>
                    </div>
                </div>

                {oil && (
                    <section className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold">
                                {fr.stock.oil.otherFormats}
                            </h2>
                            <Button
                                variant="outline"
                                className="h-11"
                                onClick={() => open('oil')}
                            >
                                <Tags />
                                {fr.stock.oil.edit}
                            </Button>
                        </div>
                        <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            {oil.formats.map((format) => (
                                <li key={format.id}>
                                    <Link
                                        href={showArticle(format.id)}
                                        className={cn(
                                            'flex h-full flex-col gap-1 rounded-xl border bg-card p-3 hover:border-primary/40',
                                            format.id === article.id &&
                                                'border-primary ring-1 ring-primary',
                                            !format.is_active && 'opacity-60',
                                        )}
                                    >
                                        <span className="font-medium">
                                            {format.label}
                                        </span>
                                        <span className="text-sm tabular-nums">
                                            {formatQuantity(
                                                format.stock_qty,
                                                format.unit,
                                            )}
                                        </span>
                                        <MoneyText
                                            value={format.sale_price}
                                            className="text-sm"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t.movements}</h2>
                    <DataTable
                        rows={movements}
                        columns={columns}
                        rowKey={(row) => row.id}
                        empty={
                            <EmptyState
                                icon={ClipboardCheck}
                                title={t.movementsEmpty}
                            />
                        }
                    />
                </section>
            </div>

            <ArticleFormSheet
                key={`edit-${openCount}`}
                open={sheet === 'edit'}
                onOpenChange={(value) => setSheet(value ? 'edit' : null)}
                article={article}
                {...options}
            />
            {oil && (
                <OilFormSheet
                    key={`oil-${openCount}`}
                    open={sheet === 'oil'}
                    onOpenChange={(value) => setSheet(value ? 'oil' : null)}
                    oil={{
                        ...oil,
                        article_category_id: article.article_category_id,
                    }}
                    {...options}
                />
            )}
            <AdjustStockSheet
                key={`adjust-${openCount}`}
                open={sheet === 'adjust'}
                onOpenChange={(value) => setSheet(value ? 'adjust' : null)}
                article={article}
            />
            <ConfirmDialog
                open={confirm === 'open'}
                onOpenChange={(value) => !value && setConfirm(null)}
                title={t.openCanTitle}
                description={t.openCanText(
                    formatQuantity(article.volume_l ?? 0),
                )}
                confirmLabel={t.openCan}
                onConfirm={() =>
                    post(ArticleController.openCan.url(article.id), 'post')
                }
            />
            <ConfirmDialog
                open={confirm === 'toggle'}
                onOpenChange={(value) => !value && setConfirm(null)}
                title={article.is_active ? t.deactivate : t.reactivate}
                description={article.name}
                confirmLabel={article.is_active ? t.deactivate : t.reactivate}
                onConfirm={() =>
                    post(ArticleController.toggle.url(article.id), 'patch')
                }
            />
        </>
    );
}
