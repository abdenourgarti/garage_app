import { Head, Link } from '@inertiajs/react';
import { Banknote, Mail, MapPin, Phone, Plus } from 'lucide-react';
import { useState } from 'react';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import { PaySupplierSheet } from '@/components/stock/pay-supplier-sheet';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { StockNav } from '@/components/stock/stock-nav';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import {
    create as createPurchase,
    show as showPurchase,
} from '@/routes/purchases';

type PurchaseRow = {
    id: number;
    status: 'draft' | 'ordered' | 'received' | 'cancelled';
    purchased_at: string;
    supplier_invoice_ref: string | null;
    total: string;
    amount_paid: string;
    remaining: string;
    is_payable: boolean;
};

type Props = {
    supplier: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        address: string | null;
        maps_url: string | null;
        notes: string | null;
        is_active: boolean;
    };
    totals: {
        purchased: string;
        paid: string;
        debt: string;
        open_orders: number;
    };
    purchases: PurchaseRow[];
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.suppliers;

export default function SupplierShow({
    supplier,
    totals,
    purchases,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const [paying, setPaying] = useState<PurchaseRow | null>(null);
    const [openCount, setOpenCount] = useState(0);

    return (
        <>
            <Head title={supplier.name} />
            <div className="space-y-6 p-4 md:p-6">
                <StockNav current="suppliers" />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {supplier.name}
                        </h1>
                        <div className="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            {supplier.phone && (
                                <a
                                    href={`tel:${supplier.phone.replace(/[^\d+]/g, '')}`}
                                    className="inline-flex min-h-11 items-center gap-1.5 font-medium text-primary tabular-nums sm:min-h-0"
                                >
                                    <Phone className="size-4" />
                                    {supplier.phone}
                                </a>
                            )}
                            {supplier.email && (
                                <a
                                    href={`mailto:${supplier.email}`}
                                    className="inline-flex min-h-11 items-center gap-1.5 text-primary sm:min-h-0"
                                >
                                    <Mail className="size-4" />
                                    {supplier.email}
                                </a>
                            )}
                            {supplier.address && (
                                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <MapPin className="size-4" />
                                    {supplier.address}
                                </span>
                            )}
                            {supplier.maps_url && (
                                <a
                                    href={supplier.maps_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex min-h-11 items-center text-primary hover:underline sm:min-h-0"
                                >
                                    {t.openMap}
                                </a>
                            )}
                        </div>
                        {supplier.notes && (
                            <p className="max-w-prose text-sm whitespace-pre-line">
                                {supplier.notes}
                            </p>
                        )}
                    </div>
                    <Button asChild className="h-11">
                        <Link
                            href={createPurchase({
                                query: { supplier: supplier.id },
                            })}
                        >
                            <Plus />
                            {t.newPurchase}
                        </Link>
                    </Button>
                </div>

                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        {
                            label: t.debt,
                            value: (
                                <MoneyText
                                    value={totals.debt}
                                    className={
                                        Number(totals.debt) > 0
                                            ? 'text-2xl text-destructive'
                                            : 'text-2xl'
                                    }
                                />
                            ),
                        },
                        {
                            label: t.purchased,
                            value: (
                                <MoneyText
                                    value={totals.purchased}
                                    className="text-xl"
                                />
                            ),
                        },
                        {
                            label: t.paid,
                            value: (
                                <MoneyText
                                    value={totals.paid}
                                    className="text-xl"
                                />
                            ),
                        },
                        {
                            label: t.openOrders,
                            value: (
                                <span className="text-xl font-semibold">
                                    {totals.open_orders}
                                </span>
                            ),
                        },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="rounded-xl border bg-card p-4"
                        >
                            <dt className="text-sm text-muted-foreground">
                                {item.label}
                            </dt>
                            <dd className="mt-1">{item.value}</dd>
                        </div>
                    ))}
                </dl>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t.purchases}</h2>
                    {purchases.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t.purchasesEmpty}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border bg-card">
                            {purchases.map((purchase) => (
                                <li
                                    key={purchase.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-2 p-3"
                                >
                                    <Link
                                        href={showPurchase(purchase.id)}
                                        className="grid min-w-36 hover:underline"
                                    >
                                        <span className="font-medium">
                                            {fr.purchases.number(purchase.id)}
                                        </span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {formatDate(purchase.purchased_at)}
                                        </span>
                                    </Link>
                                    <StatusBadge
                                        type="purchase"
                                        status={purchase.status}
                                    />
                                    <span className="ms-auto grid text-end text-sm">
                                        <MoneyText value={purchase.total} />
                                        {Number(purchase.remaining) > 0 && (
                                            <span className="text-destructive">
                                                {fr.purchases.columns.remaining}{' '}
                                                :{' '}
                                                <MoneyText
                                                    value={purchase.remaining}
                                                    className="font-semibold"
                                                />
                                            </span>
                                        )}
                                    </span>
                                    {purchase.is_payable && (
                                        <Button
                                            variant="outline"
                                            className="h-11"
                                            onClick={() => {
                                                setOpenCount(
                                                    (count) => count + 1,
                                                );
                                                setPaying(purchase);
                                            }}
                                        >
                                            <Banknote />
                                            {fr.purchases.pay}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <PaySupplierSheet
                key={openCount}
                open={paying !== null}
                onOpenChange={(open) => !open && setPaying(null)}
                purchase={
                    paying
                        ? {
                              id: paying.id,
                              remaining: paying.remaining,
                              label: `${fr.purchases.number(paying.id)}, ${supplier.name}`,
                          }
                        : null
                }
                paymentMethods={paymentMethods}
                defaultPaymentMethodId={defaultPaymentMethodId}
            />
        </>
    );
}
