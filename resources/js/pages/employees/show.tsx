import { Head, Link, router } from '@inertiajs/react';
import { HandCoins, Phone, Printer, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { AdvanceSheet } from '@/components/employees/advance-sheet';
import { MonthNav } from '@/components/employees/month-nav';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { previewSum } from '@/lib/money';
import { destroy as destroyAdvance } from '@/routes/advances';
import { show as showEmployee } from '@/routes/employees';
import { index as payrollIndex, payslip } from '@/routes/payroll';
import { show as showOrder } from '@/routes/service-orders';

type Props = {
    employee: {
        id: number;
        name: string;
        position: string | null;
        phone: string | null;
        salary_type: 'monthly' | 'daily';
        salary_amount: string;
        is_active: boolean;
    };
    period: string;
    periodLabel: string;
    works: {
        id: number;
        order_id: number;
        number: string;
        date: string;
        plate: string;
        type: string;
        labor_price: string;
    }[];
    advances: {
        id: number;
        amount: string;
        spent_at: string;
        method: string | null;
    }[];
    paid: boolean;
    payments: {
        id: number;
        period: string;
        period_label: string;
        days_worked: number | null;
        base_amount: string;
        advances_total: string;
        carried_in: string;
        net_paid: string;
        paid_at: string;
    }[];
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.employees;

export default function EmployeeShow({
    employee,
    period,
    periodLabel,
    works,
    advances,
    paid,
    payments,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const [advanceOpen, setAdvanceOpen] = useState(false);
    const [advanceCount, setAdvanceCount] = useState(0);
    const [deleting, setDeleting] = useState<number | null>(null);

    return (
        <>
            <Head title={employee.name} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {employee.name}
                        </h1>
                        <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            {employee.position && (
                                <span>{employee.position}</span>
                            )}
                            <span className="font-medium text-foreground">
                                {t.salary(
                                    formatMoney(employee.salary_amount),
                                    employee.salary_type,
                                )}
                            </span>
                            {employee.phone && (
                                <a
                                    href={`tel:${employee.phone.replace(/[^\d+]/g, '')}`}
                                    className="inline-flex min-h-11 items-center gap-1.5 font-medium text-primary tabular-nums sm:min-h-0"
                                >
                                    <Phone className="size-4" />
                                    {employee.phone}
                                </a>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 *:flex-1 sm:*:flex-none">
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
                        <Button asChild className="h-11">
                            <Link href={payrollIndex({ query: { period } })}>
                                <Wallet />
                                {t.payroll}
                            </Link>
                        </Button>
                    </div>
                </div>

                <MonthNav
                    period={period}
                    label={periodLabel}
                    href={(value) =>
                        showEmployee.url(employee.id, {
                            query: { period: value },
                        })
                    }
                />

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="rounded-xl border bg-card">
                        <h2 className="border-b p-4 font-semibold">
                            {t.works(works.length)}
                        </h2>
                        {works.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                {t.worksEmpty}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {works.map((work) => (
                                    <li key={work.id}>
                                        <Link
                                            href={showOrder(work.order_id)}
                                            className="flex flex-wrap items-center gap-x-4 gap-y-1 p-3 text-sm hover:bg-accent/50"
                                        >
                                            <span className="w-24 tabular-nums">
                                                {formatDate(work.date)}
                                            </span>
                                            <Plate plate={work.plate} />
                                            <span className="min-w-0 flex-1 font-medium">
                                                {work.type}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {work.number}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-xl border bg-card">
                        <h2 className="border-b p-4 font-semibold">
                            {t.advances}
                        </h2>
                        {advances.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                {t.advancesEmpty}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {advances.map((advance) => (
                                    <li
                                        key={advance.id}
                                        className="flex items-center gap-3 px-4 py-2 text-sm"
                                    >
                                        <span className="flex-1 tabular-nums">
                                            {formatDate(advance.spent_at)}
                                            <span className="ms-2 text-xs text-muted-foreground">
                                                {advance.method}
                                            </span>
                                        </span>
                                        <MoneyText value={advance.amount} />
                                        {!paid && (
                                            <Button
                                                variant="ghost"
                                                size="icon-lg"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    setDeleting(advance.id)
                                                }
                                                aria-label={t.deleteAdvance}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="flex justify-between border-t p-4 text-sm">
                            <span className="text-muted-foreground">
                                {paid ? t.monthPaid : t.advancesTotal}
                            </span>
                            <MoneyText
                                value={previewSum(
                                    advances.map((advance) => advance.amount),
                                )}
                            />
                        </p>
                    </section>
                </div>

                <section className="rounded-xl border bg-card">
                    <h2 className="border-b p-4 font-semibold">{t.payments}</h2>
                    {payments.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            {t.paymentsEmpty}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {payments.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1 p-3 text-sm"
                                >
                                    <span className="w-32 font-medium first-letter:uppercase">
                                        {payment.period_label}
                                    </span>
                                    <span className="min-w-0 flex-1 text-muted-foreground tabular-nums">
                                        {formatMoney(payment.base_amount)} −{' '}
                                        {formatMoney(
                                            previewSum([
                                                payment.advances_total,
                                                payment.carried_in,
                                            ]),
                                        )}{' '}
                                        = <MoneyText value={payment.net_paid} />
                                        <span className="ms-2 text-xs">
                                            {t.paidOn(
                                                formatDate(payment.paid_at),
                                            )}
                                        </span>
                                    </span>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        className="h-11"
                                    >
                                        <a
                                            href={payslip.url(
                                                {
                                                    payment: payment.id,
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
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <AdvanceSheet
                key={advanceCount}
                open={advanceOpen}
                onOpenChange={setAdvanceOpen}
                employees={[{ id: employee.id, name: employee.name }]}
                employeeId={employee.id}
                paymentMethods={paymentMethods}
                defaultPaymentMethodId={defaultPaymentMethodId}
            />
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t.deleteAdvanceTitle}
                description={t.deleteAdvanceText}
                confirmLabel={t.deleteAdvance}
                destructive
                onConfirm={() =>
                    deleting &&
                    router.delete(destroyAdvance.url(deleting), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}
