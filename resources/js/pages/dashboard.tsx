import { useGSAP } from '@gsap/react';
import { Head, Link, usePage } from '@inertiajs/react';
import gsap from 'gsap';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Car,
    ChevronRight,
    CircleCheck,
    HandCoins,
    Droplets,
    PackageMinus,
    Scale,
    Wrench,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { StatCard } from '@/components/app/stat-card';
import { StatusBadge } from '@/components/app/status-badge';
import { MonthsChart } from '@/components/dashboard/months-chart';
import type { MonthTotals } from '@/components/dashboard/months-chart';
import { remainingLabel } from '@/components/clients/next-oil-change';
import { RankingsPanel } from '@/components/dashboard/rankings';
import type { Rankings } from '@/components/dashboard/rankings';
import { duration, ease, prefersReducedMotion } from '@/lib/animations';
import {
    formatDate,
    formatMoney,
    formatQuantity,
    toIsoDate,
} from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { credits as creditsIndex } from '@/routes/invoices';
import { index as appointmentsIndex } from '@/routes/appointments';
import { show as showOrder } from '@/routes/service-orders';
import { index as remindersIndex } from '@/routes/reminders';
import { index as stockIndex } from '@/routes/stock';
import type { Appointment } from '@/types/appointments';
import type { ReminderRow } from '@/types/reminders';

gsap.registerPlugin(useGSAP);

type Totals = { incomes: string; expenses: string; profit: string };

type Props = {
    ordersToday: number;
    appointmentsToday: Appointment[];
    inProgress: {
        id: number;
        number: string;
        date: string;
        plate: string;
        vehicle: string;
        client: string;
    }[];
    lowStock: { id: number; name: string; stock: string; unit: string }[];
    dueOilChanges: ReminderRow[];
    dueOilChangesCount: number;
    rankings: Rankings;
    /** Gérant seulement : l'employé reçoit null. */
    money: {
        today: Totals;
        months: MonthTotals[];
        credits: { clients: number; total: string };
    } | null;
};

const t = fr.dashboard;

const LOW_STOCK_SHOWN = 5;

function Panel({
    title,
    action,
    className,
    children,
}: {
    title: string;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            data-reveal
            className={cn('min-w-0 rounded-xl border bg-card p-4', className)}
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="font-semibold">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export default function Dashboard({
    ordersToday,
    appointmentsToday,
    inProgress,
    lowStock,
    dueOilChanges,
    dueOilChangesCount,
    rankings,
    money,
}: Props) {
    const { auth } = usePage().props;
    const page = useRef<HTMLDivElement>(null);
    const today = toIsoDate(new Date());

    useGSAP(
        () => {
            if (!prefersReducedMotion()) {
                gsap.from('[data-reveal], [data-slot=stat-card]', {
                    opacity: 0,
                    y: 8,
                    duration: duration.base,
                    ease,
                    stagger: 0.04,
                });
            }
        },
        { scope: page },
    );

    const alerts =
        lowStock.length > 0 ||
        dueOilChanges.length > 0 ||
        (money?.credits.clients ?? 0) > 0;

    return (
        <>
            <Head title={t.title} />
            <div ref={page} className="space-y-4 p-4 md:space-y-6 md:p-6">
                <PageHeader
                    title={t.greeting(auth.user.name)}
                    description={new Date().toLocaleDateString('fr-DZ', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    })}
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {money && (
                        <>
                            <StatCard
                                label={t.incomes}
                                value={Number(money.today.incomes)}
                                kind="money"
                                icon={ArrowDownLeft}
                            />
                            <StatCard
                                label={t.expenses}
                                value={Number(money.today.expenses)}
                                kind="money"
                                icon={ArrowUpRight}
                            />
                            <StatCard
                                label={t.profit}
                                value={Number(money.today.profit)}
                                kind="money"
                                icon={Scale}
                                colorBySign
                            />
                        </>
                    )}
                    <StatCard
                        label={t.ordersToday}
                        value={ordersToday}
                        icon={Wrench}
                    />
                    {!money && (
                        <StatCard
                            label={t.inProgressCount}
                            value={inProgress.length}
                            icon={Car}
                        />
                    )}
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Panel
                        title={`${t.appointments} (${appointmentsToday.length})`}
                        className="xl:order-2"
                        action={
                            <Link
                                href={appointmentsIndex()}
                                className="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {t.seeAppointments}
                            </Link>
                        }
                    >
                        {appointmentsToday.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t.appointmentsEmpty}
                            </p>
                        ) : (
                            <ul className="max-h-96 space-y-3 overflow-y-auto">
                                {appointmentsToday.map((appointment) => (
                                    <li
                                        key={appointment.id}
                                        className="flex items-start gap-3"
                                    >
                                        <span className="w-12 shrink-0 font-semibold tabular-nums">
                                            {appointment.time}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {appointment.client.label}
                                            </span>
                                            <span className="block truncate text-sm text-muted-foreground">
                                                {appointment.vehicle
                                                    ? `${appointment.vehicle.label} · ${appointment.vehicle.description}`
                                                    : fr.appointments.noVehicle}
                                            </span>
                                        </span>
                                        <StatusBadge
                                            type="appointment"
                                            status={appointment.status}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title={`${t.inProgress} (${inProgress.length})`}
                        className="xl:order-3"
                    >
                        {inProgress.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t.inProgressEmpty}
                            </p>
                        ) : (
                            <ul className="-mx-2 max-h-96 overflow-y-auto">
                                {inProgress.map((order) => (
                                    <li key={order.id}>
                                        <Link
                                            href={showOrder(order.id)}
                                            className="flex min-h-11 items-center gap-3 rounded-md px-2 py-2 hover:bg-accent"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-mono font-semibold">
                                                    {order.plate}
                                                </span>
                                                <span className="block truncate text-sm text-muted-foreground">
                                                    {order.vehicle} ·{' '}
                                                    {order.client} ·{' '}
                                                    {order.date === today
                                                        ? t.today
                                                        : t.since(
                                                              formatDate(
                                                                  order.date,
                                                              ),
                                                          )}
                                                </span>
                                            </span>
                                            <ChevronRight
                                                className="size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title={t.alerts} className="xl:order-4">
                        {!alerts ? (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <CircleCheck
                                    className="size-4 text-success"
                                    aria-hidden="true"
                                />
                                {t.alertsEmpty}
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {lowStock.length > 0 && (
                                    <div>
                                        <p className="flex items-center gap-2 font-medium text-warning">
                                            <PackageMinus
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {t.lowStock(lowStock.length)}
                                        </p>
                                        <ul className="mt-2 space-y-1 text-sm">
                                            {lowStock
                                                .slice(0, LOW_STOCK_SHOWN)
                                                .map((article) => (
                                                    <li
                                                        key={article.id}
                                                        className="flex gap-2"
                                                    >
                                                        <span className="min-w-0 flex-1 truncate">
                                                            {article.name}
                                                        </span>
                                                        <span className="shrink-0 tabular-nums">
                                                            {formatQuantity(
                                                                article.stock,
                                                                article.unit,
                                                            )}
                                                        </span>
                                                    </li>
                                                ))}
                                        </ul>
                                        <Link
                                            href={stockIndex({
                                                query: { low: 1 },
                                            })}
                                            className="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {t.seeStock}
                                        </Link>
                                    </div>
                                )}
                                {dueOilChanges.length > 0 && (
                                    <div>
                                        <p className="flex items-center gap-2 font-medium text-warning">
                                            <Droplets
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {t.dueOilChanges(
                                                dueOilChangesCount,
                                            )}
                                        </p>
                                        <ul className="mt-2 space-y-1 text-sm">
                                            {dueOilChanges.map((reminder) => (
                                                <li
                                                    key={reminder.id}
                                                    className="flex gap-2"
                                                >
                                                    <span className="min-w-0 flex-1 truncate">
                                                        <span className="font-mono font-semibold">
                                                            {reminder.plate}
                                                        </span>{' '}
                                                        · {reminder.client}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'shrink-0',
                                                            reminder.status ===
                                                                'overdue' &&
                                                                'text-destructive',
                                                        )}
                                                    >
                                                        {remainingLabel(
                                                            reminder.remaining_km,
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                        <Link
                                            href={remindersIndex()}
                                            className="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {t.seeReminders}
                                        </Link>
                                    </div>
                                )}
                                {money && money.credits.clients > 0 && (
                                    <div>
                                        <p className="flex items-center gap-2 font-medium text-warning">
                                            <HandCoins
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {t.credits(money.credits.clients)}
                                        </p>
                                        <p className="mt-1 text-lg font-semibold tabular-nums">
                                            {formatMoney(money.credits.total)}
                                        </p>
                                        <Link
                                            href={creditsIndex()}
                                            className="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {t.seeCredits}
                                        </Link>
                                    </div>
                                )}
                            </div>
                        )}
                    </Panel>

                    <Panel title={t.rankings} className="xl:order-5">
                        <RankingsPanel rankings={rankings} />
                    </Panel>

                    {money && (
                        <Panel
                            title={t.months}
                            className="md:col-span-2 xl:order-1"
                        >
                            <p className="-mt-2 mb-3 text-sm text-muted-foreground">
                                {t.monthsHint}
                            </p>
                            <MonthsChart months={money.months} />
                        </Panel>
                    )}
                </div>
            </div>
        </>
    );
}
