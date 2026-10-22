import { Head, Link, router } from '@inertiajs/react';
import { Ban, Banknote, FileText, PackageCheck, Pencil } from 'lucide-react';
import { useState } from 'react';
import PurchaseController from '@/actions/App/Http/Controllers/Stock/PurchaseController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import { PaySupplierSheet } from '@/components/stock/pay-supplier-sheet';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { StockNav } from '@/components/stock/stock-nav';
import { Button } from '@/components/ui/button';
import { formatDate, formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showArticle } from '@/routes/articles';
import { edit as editPurchase } from '@/routes/purchases';
import { show as showSupplier } from '@/routes/suppliers';

type Props = {
    purchase: {
        id: number;
        status: 'draft' | 'ordered' | 'received' | 'cancelled';
        purchased_at: string;
        received_at: string | null;
        supplier_invoice_ref: string | null;
        total: string;
        amount_paid: string;
        remaining: string;
        payment_status: 'payee' | 'partielle' | 'impayee' | null;
        supplier: { id: number; name: string; phone: string | null };
        notes: string | null;
        document_url: string | null;
        user: string;
        is_editable: boolean;
        is_payable: boolean;
        items: {
            id: number;
            article_id: number;
            name: string;
            unit: string;
            quantity: string;
            unit_price: string;
            line_total: string;
        }[];
        payments: {
            id: number;
            amount: string;
            paid_at: string;
            method: string;
            user: string;
            proof_url: string | null;
        }[];
    };
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.purchases;

export default function PurchaseShow({
    purchase,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const [confirm, setConfirm] = useState<'receive' | 'cancel' | null>(null);
    const [paying, setPaying] = useState(false);
    const [openCount, setOpenCount] = useState(0);
    const canPay = purchase.is_payable && Number(purchase.remaining) > 0;

    const action = (url: string) =>
        router.post(
            url,
            {},
            { preserveScroll: true, onFinish: () => setConfirm(null) },
        );

    return (
        <>
            <Head title={t.number(purchase.id)} />
            <div className="space-y-6 p-4 md:p-6">
                <StockNav current="purchases" />

                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <h1 className="flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight">
                            {t.number(purchase.id)}
                            <StatusBadge
                                type="purchase"
                                status={purchase.status}
                                className="text-sm"
                            />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={showSupplier(purchase.supplier.id)}
                                className="font-medium text-foreground hover:underline"
                            >
                                {purchase.supplier.name}
                            </Link>
                            {', '}
                            {formatDate(purchase.purchased_at)}
                            {purchase.supplier_invoice_ref &&
                                `, ${t.fields.invoiceRef} ${purchase.supplier_invoice_ref}`}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {[
                                purchase.received_at &&
                                    t.receivedOn(
                                        formatDate(purchase.received_at),
                                    ),
                                t.createdBy(purchase.user),
                            ]
                                .filter(Boolean)
                                .join(', ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                        {purchase.is_editable && (
                            <Button
                                className="h-11"
                                onClick={() => setConfirm('receive')}
                            >
                                <PackageCheck />
                                {t.receive}
                            </Button>
                        )}
                        {canPay && (
                            <Button
                                className="h-11"
                                variant={
                                    purchase.is_editable ? 'outline' : 'default'
                                }
                                onClick={() => {
                                    setOpenCount((count) => count + 1);
                                    setPaying(true);
                                }}
                            >
                                <Banknote />
                                {t.pay}
                            </Button>
                        )}
                        {purchase.is_editable && (
                            <Button asChild className="h-11" variant="outline">
                                <Link href={editPurchase(purchase.id)}>
                                    <Pencil />
                                    {t.edit}
                                </Link>
                            </Button>
                        )}
                        {purchase.document_url && (
                            <Button asChild className="h-11" variant="outline">
                                <a
                                    href={purchase.document_url}
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <FileText />
                                    {t.document}
                                </a>
                            </Button>
                        )}
                        {purchase.is_editable && (
                            <Button
                                className="h-11 text-destructive hover:text-destructive"
                                variant="outline"
                                onClick={() => setConfirm('cancel')}
                            >
                                <Ban />
                                {t.cancel}
                            </Button>
                        )}
                    </div>
                </div>

                <dl className="grid grid-cols-3 gap-3 rounded-xl border bg-card p-4">
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            {t.columns.total}
                        </dt>
                        <dd>
                            <MoneyText
                                value={purchase.total}
                                className="text-xl"
                            />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            {t.columns.paid}
                        </dt>
                        <dd>
                            <MoneyText
                                value={purchase.amount_paid}
                                className="text-xl"
                            />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            {t.columns.remaining}
                        </dt>
                        <dd className="flex flex-wrap items-center gap-2">
                            <MoneyText
                                value={purchase.remaining}
                                className={
                                    Number(purchase.remaining) > 0
                                        ? 'text-xl text-destructive'
                                        : 'text-xl'
                                }
                            />
                            {purchase.payment_status && (
                                <StatusBadge
                                    type="invoice"
                                    status={purchase.payment_status}
                                />
                            )}
                        </dd>
                    </div>
                </dl>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t.lines}</h2>
                    <ul className="divide-y rounded-xl border bg-card">
                        {purchase.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 p-3"
                            >
                                <Link
                                    href={showArticle(item.article_id)}
                                    className="min-w-0 font-medium hover:underline"
                                >
                                    {item.name}
                                </Link>
                                <span className="ms-auto text-sm text-muted-foreground tabular-nums">
                                    {formatQuantity(item.quantity, item.unit)} ×{' '}
                                    <MoneyText
                                        value={item.unit_price}
                                        className="font-normal"
                                    />
                                </span>
                                <MoneyText
                                    value={item.line_total}
                                    className="w-28 text-end"
                                />
                            </li>
                        ))}
                    </ul>
                    {purchase.notes && (
                        <p className="max-w-prose text-sm whitespace-pre-line">
                            {purchase.notes}
                        </p>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t.payments}</h2>
                    {purchase.payments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t.paymentsEmpty}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border bg-card">
                            {purchase.payments.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 p-3 text-sm"
                                >
                                    <span className="tabular-nums">
                                        {formatDate(payment.paid_at)}
                                    </span>
                                    <span>{payment.method}</span>
                                    <span className="text-muted-foreground">
                                        {payment.user}
                                    </span>
                                    {payment.proof_url ? (
                                        <a
                                            href={payment.proof_url}
                                            target="_blank"
                                            rel="noopener"
                                            className="inline-flex min-h-11 items-center gap-1 text-primary hover:underline"
                                        >
                                            <FileText className="size-4" />
                                            {t.proof}
                                        </a>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                    <MoneyText
                                        value={payment.amount}
                                        className="ms-auto"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <PaySupplierSheet
                key={openCount}
                open={paying}
                onOpenChange={setPaying}
                purchase={{
                    id: purchase.id,
                    remaining: purchase.remaining,
                    label: `${t.number(purchase.id)}, ${purchase.supplier.name}`,
                }}
                paymentMethods={paymentMethods}
                defaultPaymentMethodId={defaultPaymentMethodId}
            />
            <ConfirmDialog
                open={confirm === 'receive'}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={t.receiveTitle}
                description={t.receiveText}
                confirmLabel={t.receive}
                onConfirm={() =>
                    action(PurchaseController.receive.url(purchase.id))
                }
            />
            <ConfirmDialog
                open={confirm === 'cancel'}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={t.cancelTitle}
                description={t.cancelText}
                confirmLabel={t.cancel}
                destructive
                onConfirm={() =>
                    action(PurchaseController.cancel.url(purchase.id))
                }
            />
        </>
    );
}
