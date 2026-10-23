import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, FileText, Pencil, Phone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ServiceOrderController from '@/actions/App/Http/Controllers/ServiceOrders/ServiceOrderController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showArticle } from '@/routes/articles';
import { show as showClient } from '@/routes/clients';
import { show as showInvoice } from '@/routes/invoices';
import { edit as editOrder } from '@/routes/service-orders';
import { show as showVehicle } from '@/routes/vehicles';
import type { InvoiceStatus } from '@/types/invoices';

type Props = {
    order: {
        id: number;
        number: string;
        date: string;
        status: 'en_cours' | 'terminee';
        plate: string;
        vehicle_label: string;
        vehicle_id: number;
        client: { id: number; full_name: string; phone: string };
        mileage: number | null;
        immobilization: string | null;
        notes: string | null;
        advice: string | null;
        completed_at: string | null;
        user: string;
        lines: {
            id: number;
            type: string;
            employee: string | null;
            description: string | null;
            labor_price: string;
            parts: {
                id: number;
                origin: string;
                origin_label: string;
                article_id: number | null;
                designation: string;
                quantity: string;
                unit: string;
                unit_price: string;
                line_total: string;
            }[];
        }[];
        labor_total: string;
        parts_total: string;
        total: string;
        can_delete: boolean;
        invoices: {
            id: number;
            number: string;
            total: string;
            status: InvoiceStatus;
        }[];
    };
};

const t = fr.serviceOrders;

export default function ServiceOrderShow({ order }: Props) {
    const [confirm, setConfirm] = useState<'complete' | 'delete' | null>(null);
    const inProgress = order.status === 'en_cours';
    const activeInvoice = order.invoices.find(
        (invoice) => invoice.status !== 'annulee',
    );
    const cancelledInvoices = order.invoices.filter(
        (invoice) => invoice.status === 'annulee',
    );

    return (
        <>
            <Head title={order.number} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <h1 className="flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight">
                            {order.number}
                            <StatusBadge
                                type="serviceOrder"
                                status={order.status}
                                className="text-sm"
                            />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {[
                                formatDate(order.date),
                                order.completed_at &&
                                    t.completedOn(
                                        formatDate(order.completed_at),
                                    ),
                                t.createdBy(order.user),
                            ]
                                .filter(Boolean)
                                .join(', ')}
                        </p>
                        {cancelledInvoices.length > 0 && (
                            <p className="flex flex-wrap gap-x-3 text-sm text-muted-foreground">
                                {cancelledInvoices.map((invoice) => (
                                    <Link
                                        key={invoice.id}
                                        href={showInvoice(invoice.id)}
                                        className="line-through hover:underline"
                                    >
                                        {`${fr.invoices.invoice} ${invoice.number}`}
                                    </Link>
                                ))}
                            </p>
                        )}
                    </div>
                    {activeInvoice && (
                        <Button asChild className="h-11">
                            <Link href={showInvoice(activeInvoice.id)}>
                                <FileText />
                                {`${fr.invoices.invoice} ${activeInvoice.number}`}
                            </Link>
                        </Button>
                    )}
                    {inProgress && (
                        <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                            <Button
                                className="h-11"
                                onClick={() => setConfirm('complete')}
                            >
                                <CheckCircle2 />
                                {t.complete}
                            </Button>
                            <Button asChild variant="outline" className="h-11">
                                <Link href={editOrder(order.id)}>
                                    <Pencil />
                                    {t.edit}
                                </Link>
                            </Button>
                            {order.can_delete && (
                                <Button
                                    variant="outline"
                                    className="h-11 text-destructive hover:text-destructive"
                                    onClick={() => setConfirm('delete')}
                                >
                                    <Trash2 />
                                    {t.delete}
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Link
                        href={showVehicle(order.vehicle_id)}
                        className="grid gap-2 rounded-xl border bg-card p-4 hover:border-primary/40"
                    >
                        <Plate
                            plate={order.plate}
                            className="self-start text-base"
                        />
                        <span className="font-medium">
                            {order.vehicle_label}
                        </span>
                    </Link>
                    <div className="grid gap-1 rounded-xl border bg-card p-4">
                        <Link
                            href={showClient(order.client.id)}
                            className="font-semibold hover:underline"
                        >
                            {order.client.full_name}
                        </Link>
                        <a
                            href={`tel:${order.client.phone.replace(/[^\d+]/g, '')}`}
                            className="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary tabular-nums"
                        >
                            <Phone className="size-4" />
                            {order.client.phone}
                        </a>
                    </div>
                    <dl className="grid grid-cols-2 gap-2 rounded-xl border bg-card p-4 text-sm">
                        <dt className="text-muted-foreground">{t.mileage}</dt>
                        <dd className="text-end font-medium tabular-nums">
                            {order.mileage === null
                                ? '—'
                                : formatQuantity(order.mileage, 'km')}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t.immobilization}
                        </dt>
                        <dd className="text-end font-medium">
                            {order.immobilization ?? '—'}
                        </dd>
                    </dl>
                </div>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t.sections.works}
                    </h2>
                    {order.lines.map((line) => (
                        <article
                            key={line.id}
                            className="rounded-xl border bg-card"
                        >
                            <header className="flex flex-wrap items-center justify-between gap-2 border-b p-3">
                                <div>
                                    <h3 className="font-semibold">
                                        {line.type}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {t.mechanic} :{' '}
                                        {line.employee ?? t.mechanicNone}
                                    </p>
                                </div>
                                <span className="text-sm">
                                    {t.labor}{' '}
                                    <MoneyText value={line.labor_price} />
                                </span>
                            </header>
                            {line.parts.length > 0 && (
                                <ul className="divide-y">
                                    {line.parts.map((part) => (
                                        <li
                                            key={part.id}
                                            className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 p-3 text-sm"
                                        >
                                            <span className="grid min-w-0">
                                                {part.article_id ? (
                                                    <Link
                                                        href={showArticle(
                                                            part.article_id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {part.designation}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">
                                                        {part.designation}
                                                    </span>
                                                )}
                                                <span className="text-xs text-muted-foreground">
                                                    {part.origin_label}
                                                </span>
                                            </span>
                                            <span className="ms-auto text-muted-foreground tabular-nums">
                                                {formatQuantity(
                                                    part.quantity,
                                                    part.unit,
                                                )}
                                                {part.origin !== 'client' && (
                                                    <>
                                                        {' × '}
                                                        <MoneyText
                                                            value={
                                                                part.unit_price
                                                            }
                                                            className="font-normal"
                                                        />
                                                    </>
                                                )}
                                            </span>
                                            <MoneyText
                                                value={part.line_total}
                                                className="w-28 text-end"
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </article>
                    ))}
                </section>

                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-3">
                        {order.advice && (
                            <div className="rounded-xl border border-highlight/40 bg-highlight/10 p-4">
                                <h2 className="text-sm font-semibold">
                                    {t.sections.advice}
                                </h2>
                                <p className="mt-1 whitespace-pre-line">
                                    {order.advice}
                                </p>
                            </div>
                        )}
                        {order.notes && (
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {order.notes}
                            </p>
                        )}
                    </div>
                    <dl className="grid gap-1 rounded-xl border bg-card p-4 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">
                                {t.laborTotal}
                            </dt>
                            <dd>
                                <MoneyText value={order.labor_total} />
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">
                                {t.partsTotal}
                            </dt>
                            <dd>
                                <MoneyText value={order.parts_total} />
                            </dd>
                        </div>
                        <div className="flex items-baseline justify-between border-t pt-2">
                            <dt className="font-semibold">{t.total}</dt>
                            <dd>
                                <MoneyText
                                    value={order.total}
                                    className="text-2xl"
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <ConfirmDialog
                open={confirm === 'complete'}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={t.completeTitle}
                description={t.completeText}
                confirmLabel={t.complete}
                onConfirm={() =>
                    router.post(
                        ServiceOrderController.complete.url(order.id),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setConfirm(null),
                        },
                    )
                }
            />
            <ConfirmDialog
                open={confirm === 'delete'}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={t.deleteTitle}
                description={t.deleteText}
                confirmLabel={t.delete}
                destructive
                onConfirm={() =>
                    router.delete(
                        ServiceOrderController.destroy.url(order.id),
                        { onFinish: () => setConfirm(null) },
                    )
                }
            />
        </>
    );
}
