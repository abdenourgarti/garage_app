import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Ban,
    Download,
    HandCoins,
    Paperclip,
    Percent,
    Phone,
    Printer,
    ReceiptText,
} from 'lucide-react';
import { useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/Invoices/InvoiceController';
import PaymentController from '@/actions/App/Http/Controllers/Invoices/PaymentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { PaySupplierSheet } from '@/components/stock/pay-supplier-sheet';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatTime } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { show as showClient } from '@/routes/clients';
import { document as invoiceDocument } from '@/routes/invoices';
import { proof, receipt } from '@/routes/payments';
import { show as showOrder } from '@/routes/service-orders';
import { show as showVehicle } from '@/routes/vehicles';
import type { InvoiceRow } from '@/types/invoices';

type Props = {
    invoice: InvoiceRow & {
        labor_total: string;
        parts_total: string;
        discount: string;
        client: { id: number; full_name: string; phone: string };
        order: { id: number; number: string };
        vehicle_id: number;
        vehicle_label: string;
        cancelled_at: string | null;
        cancel_reason: string | null;
        cancelled_by: string | null;
        payments: {
            id: number;
            amount: string;
            paid_at: string;
            method: string;
            user: string;
            has_proof: boolean;
            cancelled: boolean;
        }[];
        can_pay: boolean;
        can_discount: boolean;
        can_cancel: boolean;
    };
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.invoices;

function DiscountSheet({
    invoice,
    open,
    onOpenChange,
}: {
    invoice: Props['invoice'];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        discount: Number(invoice.discount) ? invoice.discount : '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ discount: data.discount || '0' }));
        form.submit(InvoiceController.discount(invoice.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{t.discountTitle}</SheetTitle>
                        <SheetDescription>{t.discountText}</SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        <FormField
                            id="invoice-discount"
                            label={t.discount}
                            error={form.errors.discount}
                        >
                            <MoneyInput
                                id="invoice-discount"
                                value={form.data.discount}
                                onValueChange={(value) =>
                                    form.setData('discount', value)
                                }
                                aria-invalid={Boolean(form.errors.discount)}
                            />
                        </FormField>
                    </div>
                    <SheetFooter className="mt-auto flex-row gap-2 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 flex-1"
                            onClick={() => onOpenChange(false)}
                        >
                            {fr.common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            className="h-11 flex-1"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {t.discountSave}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function InvoiceShow({
    invoice,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const [sheet, setSheet] = useState<'pay' | 'discount' | null>(null);
    const [openCount, setOpenCount] = useState(0);
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [previewHeight, setPreviewHeight] = useState(900);
    const cancelled = invoice.status === 'annulee';

    const open = (target: 'pay' | 'discount') => {
        setOpenCount((count) => count + 1);
        setSheet(target);
    };

    const printUrl = (format: 'a4' | 'ticket') =>
        invoiceDocument.url(
            { invoice: invoice.id, format },
            { query: { print: 1 } },
        );

    const cancel = (reason: string) =>
        router.post(
            InvoiceController.cancel.url(invoice.id),
            { reason },
            {
                preserveScroll: true,
                onStart: () => setCancelling(true),
                onFinish: () => {
                    setCancelling(false);
                    setConfirmCancel(false);
                },
            },
        );

    return (
        <>
            <Head title={invoice.number} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="space-y-2">
                    <h1 className="flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight">
                        <span className={cn(cancelled && 'line-through')}>
                            {invoice.number}
                        </span>
                        <StatusBadge
                            type="invoice"
                            status={invoice.status}
                            className="text-sm"
                        />
                    </h1>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                        <span>{t.issuedOn(formatDate(invoice.issued_at))}</span>
                        <Link
                            href={showOrder(invoice.order.id)}
                            className="font-medium text-primary hover:underline"
                        >
                            {t.order(invoice.order.number)}
                        </Link>
                        <Link href={showVehicle(invoice.vehicle_id)}>
                            <Plate plate={invoice.plate} />
                        </Link>
                        <Link
                            href={showClient(invoice.client.id)}
                            className="font-medium text-foreground hover:underline"
                        >
                            {invoice.client.full_name}
                        </Link>
                        <a
                            href={`tel:${invoice.client.phone.replace(/[^\d+]/g, '')}`}
                            className="inline-flex min-h-11 items-center gap-1.5 font-medium text-primary tabular-nums sm:min-h-0"
                            aria-label={`${fr.clients.call} ${invoice.client.phone}`}
                        >
                            <Phone className="size-4" />
                            {invoice.client.phone}
                        </a>
                    </div>
                </div>

                {cancelled && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm">
                        <p className="font-semibold text-destructive">
                            {t.cancelledOn(
                                formatDate(invoice.cancelled_at ?? ''),
                                invoice.cancelled_by ?? '',
                            )}
                        </p>
                        {invoice.cancel_reason && (
                            <p className="mt-1">
                                {t.cancelReason(invoice.cancel_reason)}
                            </p>
                        )}
                    </div>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <aside className="space-y-4 lg:sticky lg:top-20 lg:order-2">
                        <div className="space-y-4 rounded-xl border bg-card p-4">
                            <dl className="grid gap-1 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        {t.total}
                                    </dt>
                                    <dd>
                                        <MoneyText value={invoice.total} />
                                    </dd>
                                </div>
                                {Number(invoice.discount) > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            {t.discount}
                                        </dt>
                                        <dd>
                                            −{' '}
                                            <MoneyText
                                                value={invoice.discount}
                                            />
                                        </dd>
                                    </div>
                                )}
                                <div
                                    className={cn(
                                        'flex justify-between',
                                        cancelled && 'hidden',
                                    )}
                                >
                                    <dt className="text-muted-foreground">
                                        {t.paid}
                                    </dt>
                                    <dd>
                                        <MoneyText
                                            value={invoice.amount_paid}
                                        />
                                    </dd>
                                </div>
                                {!cancelled && (
                                    <div className="flex items-baseline justify-between border-t pt-2">
                                        <dt className="font-semibold">
                                            {t.remaining}
                                        </dt>
                                        <dd>
                                            <MoneyText
                                                value={invoice.remaining}
                                                className={cn(
                                                    'text-2xl',
                                                    Number(invoice.remaining) >
                                                        0 && 'text-destructive',
                                                )}
                                            />
                                        </dd>
                                    </div>
                                )}
                            </dl>
                            {invoice.can_pay && (
                                <Button
                                    className="h-12 w-full text-base"
                                    onClick={() => open('pay')}
                                >
                                    <HandCoins />
                                    {t.pay}
                                </Button>
                            )}
                            <div className="grid grid-cols-3 gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11 px-2"
                                >
                                    <a
                                        href={printUrl('a4')}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Printer />
                                        A4
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11 px-2"
                                >
                                    <a
                                        href={printUrl('ticket')}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={t.printTicket}
                                    >
                                        <ReceiptText />
                                        80 mm
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11 px-2"
                                >
                                    <a
                                        href={invoiceDocument.url({
                                            invoice: invoice.id,
                                            format: 'pdf',
                                        })}
                                    >
                                        <Download />
                                        {t.pdf}
                                    </a>
                                </Button>
                            </div>
                            {(invoice.can_discount || invoice.can_cancel) && (
                                <div className="flex flex-wrap gap-2 [&>*]:flex-1">
                                    {invoice.can_discount && (
                                        <Button
                                            variant="outline"
                                            className="h-11"
                                            onClick={() => open('discount')}
                                        >
                                            <Percent />
                                            {t.discountTitle}
                                        </Button>
                                    )}
                                    {invoice.can_cancel && (
                                        <Button
                                            variant="outline"
                                            className="h-11 text-destructive hover:text-destructive"
                                            onClick={() =>
                                                setConfirmCancel(true)
                                            }
                                        >
                                            <Ban />
                                            {t.cancel}
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>

                        <section className="rounded-xl border bg-card">
                            <h2 className="border-b p-4 font-semibold">
                                {t.payments}
                            </h2>
                            {invoice.payments.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">
                                    {t.paymentsEmpty}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {invoice.payments.map((payment) => (
                                        <li
                                            key={payment.id}
                                            className={cn(
                                                'grid gap-2 p-4 text-sm',
                                                payment.cancelled &&
                                                    'opacity-60',
                                            )}
                                        >
                                            <div className="flex items-baseline justify-between gap-2">
                                                <span className="grid">
                                                    <span className="font-medium">
                                                        {payment.method}
                                                        {payment.cancelled &&
                                                            ` · ${t.cancelledPayment}`}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {formatDate(
                                                            payment.paid_at,
                                                        )}{' '}
                                                        {formatTime(
                                                            payment.paid_at,
                                                        )}{' '}
                                                        {t.byUser(payment.user)}
                                                    </span>
                                                </span>
                                                <MoneyText
                                                    value={payment.amount}
                                                    className={cn(
                                                        payment.cancelled &&
                                                            'line-through',
                                                    )}
                                                />
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-9"
                                                >
                                                    <a
                                                        href={receipt.url(
                                                            {
                                                                payment:
                                                                    payment.id,
                                                                format: 'a4',
                                                            },
                                                            {
                                                                query: {
                                                                    print: 1,
                                                                },
                                                            },
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Printer />
                                                        {t.receipt}
                                                    </a>
                                                </Button>
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-9"
                                                >
                                                    <a
                                                        href={receipt.url(
                                                            {
                                                                payment:
                                                                    payment.id,
                                                                format: 'ticket',
                                                            },
                                                            {
                                                                query: {
                                                                    print: 1,
                                                                },
                                                            },
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <ReceiptText />
                                                        {t.receiptTicket}
                                                    </a>
                                                </Button>
                                                {payment.has_proof && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-9"
                                                    >
                                                        <a
                                                            href={proof.url(
                                                                payment.id,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <Paperclip />
                                                            {t.proof}
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </aside>

                    <section className="space-y-2 lg:order-1">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {t.preview}
                        </h2>
                        {/* Le document lui-même (même page que l'impression et le PDF), dans un cadre à sa hauteur. */}
                        <iframe
                            key={`${invoice.status}-${invoice.amount_paid}-${invoice.total}`}
                            title={t.preview}
                            src={invoiceDocument.url({
                                invoice: invoice.id,
                                format: 'a4',
                            })}
                            className="w-full rounded-xl border bg-white shadow-sm"
                            style={{ height: previewHeight }}
                            onLoad={(event) => {
                                // La hauteur du document, pas celle du cadre (qui vaut au moins la hauteur actuelle).
                                const page =
                                    event.currentTarget.contentDocument?.querySelector<HTMLElement>(
                                        '.page',
                                    );

                                if (page) {
                                    setPreviewHeight(page.offsetHeight + 4);
                                }
                            }}
                        />
                    </section>
                </div>
            </div>

            <PaySupplierSheet
                key={`pay-${openCount}`}
                open={sheet === 'pay'}
                onOpenChange={(value) => !value && setSheet(null)}
                target={{
                    id: invoice.id,
                    remaining: invoice.remaining,
                    label: `${invoice.number}, ${invoice.client.full_name}`,
                }}
                url={PaymentController.store.url(invoice.id)}
                title={fr.payment.clientTitle}
                submitLabel={fr.payment.clientSubmit}
                paymentMethods={paymentMethods}
                defaultPaymentMethodId={defaultPaymentMethodId}
            />
            <DiscountSheet
                key={`discount-${openCount}`}
                invoice={invoice}
                open={sheet === 'discount'}
                onOpenChange={(value) => !value && setSheet(null)}
            />
            <ConfirmDialog
                open={confirmCancel}
                onOpenChange={setConfirmCancel}
                title={t.cancelTitle}
                description={t.cancelText}
                confirmLabel={t.cancel}
                destructive
                requireReason
                processing={cancelling}
                onConfirm={cancel}
            />
        </>
    );
}
