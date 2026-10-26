import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    HandCoins,
    Printer,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { AdvanceSheet } from '@/components/employees/advance-sheet';
import { MonthNav } from '@/components/employees/month-nav';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatMoney, toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { previewLineTotal, previewSum } from '@/lib/money';
import { cn } from '@/lib/utils';
import { show as showEmployee } from '@/routes/employees';
import {
    index as payrollIndex,
    pay as payRoute,
    payslip,
} from '@/routes/payroll';

type Row = {
    id: number;
    name: string;
    salary_type: 'monthly' | 'daily';
    salary_amount: string;
    suggested_days: number | null;
    pay: {
        base: string;
        advances: string;
        carried_in: string;
        due: string;
        net: string;
        excess: string;
        days_worked: number | null;
    } | null;
    payment: {
        id: number;
        days_worked: number | null;
        base_amount: string;
        advances_total: string;
        carried_in: string;
        net_paid: string;
        paid_at: string;
    } | null;
};

type Props = {
    period: string;
    periodLabel: string;
    isFuture: boolean;
    rows: Row[];
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.employees;

/** Calcul affiché (le serveur refait le même au paiement) : base − (avances + report), jamais négatif. */
function amounts(row: Row, days: string) {
    const base =
        row.salary_type === 'daily'
            ? previewLineTotal(days || 0, row.salary_amount)
            : Number(row.pay?.base ?? 0);
    const due = Number(row.pay?.due ?? 0);
    const rest = previewSum([base, -due]);

    return { base, due, net: Math.max(0, rest), excess: Math.max(0, -rest) };
}

function PayDialog({
    row,
    days,
    period,
    periodLabel,
    paymentMethods,
    defaultPaymentMethodId,
    onClose,
}: {
    row: Row;
    days: string;
    period: string;
    periodLabel: string;
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
    onClose: () => void;
}) {
    const today = toIsoDate(new Date());
    const { net, excess } = amounts(row, days);
    const form = useForm({
        period,
        days_worked: row.salary_type === 'daily' ? days : '',
        paid_at: today,
        payment_method_id: defaultPaymentMethodId
            ? String(defaultPaymentMethodId)
            : '',
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(payRoute.url(row.id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {t.payTitle(row.name, periodLabel)}
                        </DialogTitle>
                        <DialogDescription>
                            {t.net} : <MoneyText value={net} />
                        </DialogDescription>
                    </DialogHeader>
                    {excess > 0 && (
                        <p className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                            {t.excess(formatMoney(excess))}
                        </p>
                    )}
                    <div className="grid grid-cols-2 gap-3">
                        <FormField
                            id="pay-date"
                            label={t.paidAt}
                            required
                            error={form.errors.paid_at}
                        >
                            <Input
                                id="pay-date"
                                type="date"
                                className="h-11"
                                max={today}
                                value={form.data.paid_at}
                                onChange={(event) =>
                                    form.setData('paid_at', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="pay-method"
                            label={t.method}
                            required
                            error={form.errors.payment_method_id}
                        >
                            <Select
                                value={form.data.payment_method_id}
                                onValueChange={(value) =>
                                    form.setData('payment_method_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="pay-method"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethods.map((method) => (
                                        <SelectItem
                                            key={method.id}
                                            value={String(method.id)}
                                        >
                                            {method.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>
                    {(form.errors.period || form.errors.days_worked) && (
                        <p className="text-sm text-destructive">
                            {form.errors.period ?? form.errors.days_worked}
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            onClick={onClose}
                        >
                            {fr.common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            className="h-11"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {t.confirmPay}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Payroll({
    period,
    periodLabel,
    isFuture,
    rows,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const [days, setDays] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            rows.map((row) => [row.id, String(row.suggested_days ?? '')]),
        ),
    );
    const [paying, setPaying] = useState<Row | null>(null);
    const [advanceOpen, setAdvanceOpen] = useState(false);
    const [advanceCount, setAdvanceCount] = useState(0);

    return (
        <>
            <Head title={t.payrollTitle(periodLabel)} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={t.payrollTitle(periodLabel)}
                    description={t.payrollDescription}
                    actions={
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() => {
                                setAdvanceCount((count) => count + 1);
                                setAdvanceOpen(true);
                            }}
                        >
                            <HandCoins />
                            {t.addAdvance}
                        </Button>
                    }
                />
                <MonthNav
                    period={period}
                    label={periodLabel}
                    href={(value) =>
                        payrollIndex.url({ query: { period: value } })
                    }
                />
                {isFuture && (
                    <p className="rounded-lg border bg-muted p-3 text-sm text-muted-foreground">
                        {t.future}
                    </p>
                )}

                {rows.length === 0 ? (
                    <EmptyState icon={Wallet} title={t.payrollEmpty} />
                ) : (
                    <ul className="grid gap-3">
                        {rows.map((row) => {
                            const daily = row.salary_type === 'daily';
                            const { base, due, net, excess } = amounts(
                                row,
                                days[row.id] ?? '',
                            );

                            return (
                                <li
                                    key={row.id}
                                    className={cn(
                                        'grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto] md:items-center',
                                        excess > 0 &&
                                            !row.payment &&
                                            'border-warning/50',
                                    )}
                                >
                                    <Link
                                        href={showEmployee.url(row.id, {
                                            query: { period },
                                        })}
                                        className="font-semibold hover:underline"
                                    >
                                        {row.name}
                                        <span className="block text-xs font-normal text-muted-foreground">
                                            {t.salary(
                                                formatMoney(row.salary_amount),
                                                row.salary_type,
                                            )}
                                        </span>
                                    </Link>

                                    {row.payment ? (
                                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                            <CheckCircle2 className="size-4 text-success" />
                                            <span>
                                                {t.paidOn(
                                                    formatDate(
                                                        row.payment.paid_at,
                                                    ),
                                                )}
                                            </span>
                                            <span className="text-muted-foreground tabular-nums">
                                                {formatMoney(
                                                    row.payment.base_amount,
                                                )}{' '}
                                                −{' '}
                                                {formatMoney(
                                                    previewSum([
                                                        row.payment
                                                            .advances_total,
                                                        row.payment.carried_in,
                                                    ]),
                                                )}{' '}
                                                =
                                            </span>
                                            <MoneyText
                                                value={row.payment.net_paid}
                                            />
                                        </p>
                                    ) : (
                                        <div className="grid gap-2">
                                            <dl className="grid grid-cols-3 gap-2 text-sm">
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        {t.base}
                                                    </dt>
                                                    <dd className="flex flex-wrap items-center gap-1">
                                                        {daily ? (
                                                            <>
                                                                <Input
                                                                    aria-label={
                                                                        t.daysWorked
                                                                    }
                                                                    inputMode="numeric"
                                                                    maxLength={
                                                                        2
                                                                    }
                                                                    className="h-9 w-14 text-center"
                                                                    value={
                                                                        days[
                                                                            row
                                                                                .id
                                                                        ] ?? ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setDays(
                                                                            {
                                                                                ...days,
                                                                                [row.id]:
                                                                                    event.target.value.replace(
                                                                                        /\D/g,
                                                                                        '',
                                                                                    ),
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                                <span className="text-xs text-muted-foreground">
                                                                    {t.perDay(
                                                                        formatMoney(
                                                                            row.salary_amount,
                                                                        ),
                                                                    )}
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <MoneyText
                                                                value={base}
                                                                className="font-normal"
                                                            />
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        {t.advancesShort}
                                                    </dt>
                                                    <dd>
                                                        <MoneyText
                                                            value={due}
                                                            className="font-normal"
                                                        />
                                                        {Number(
                                                            row.pay?.carried_in,
                                                        ) > 0 && (
                                                            <span className="block text-xs text-muted-foreground">
                                                                {t.carried(
                                                                    formatMoney(
                                                                        row.pay
                                                                            ?.carried_in ??
                                                                            0,
                                                                    ),
                                                                )}
                                                            </span>
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        {t.net}
                                                    </dt>
                                                    <dd>
                                                        <MoneyText
                                                            value={net}
                                                            className="text-base"
                                                        />
                                                    </dd>
                                                </div>
                                            </dl>
                                            {daily &&
                                                row.suggested_days !== null && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {t.daysHint(
                                                            row.suggested_days,
                                                        )}
                                                    </p>
                                                )}
                                            {excess > 0 && (
                                                <p className="flex items-start gap-2 text-sm text-warning">
                                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                                    {t.excess(
                                                        formatMoney(excess),
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    <div className="flex justify-end">
                                        {row.payment ? (
                                            <Button
                                                asChild
                                                variant="ghost"
                                                className="h-11"
                                            >
                                                <a
                                                    href={payslip.url(
                                                        {
                                                            payment:
                                                                row.payment.id,
                                                            format: 'a4',
                                                        },
                                                        { query: { print: 1 } },
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Printer />
                                                    {t.payslip}
                                                </a>
                                            </Button>
                                        ) : (
                                            <Button
                                                className="h-11 w-full md:w-auto"
                                                disabled={
                                                    isFuture ||
                                                    (daily &&
                                                        days[row.id] === '')
                                                }
                                                onClick={() => setPaying(row)}
                                            >
                                                <Wallet />
                                                {t.pay}
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {paying && (
                <PayDialog
                    row={paying}
                    days={days[paying.id] ?? ''}
                    period={period}
                    periodLabel={periodLabel}
                    paymentMethods={paymentMethods}
                    defaultPaymentMethodId={defaultPaymentMethodId}
                    onClose={() => setPaying(null)}
                />
            )}
            <AdvanceSheet
                key={advanceCount}
                open={advanceOpen}
                onOpenChange={setAdvanceOpen}
                employees={rows.map((row) => ({ id: row.id, name: row.name }))}
                employeeId={null}
                paymentMethods={paymentMethods}
                defaultPaymentMethodId={defaultPaymentMethodId}
            />
        </>
    );
}
