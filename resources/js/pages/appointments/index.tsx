import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { AppointmentCard } from '@/components/appointments/appointment-card';
import { AppointmentSheet } from '@/components/appointments/appointment-sheet';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { index as appointmentsIndex } from '@/routes/appointments';
import type { Appointment } from '@/types/appointments';
import type { ClientVehicleOptions } from '@/types/clients';

type View = 'day' | 'week' | 'month';

type Props = ClientVehicleOptions & {
    view: View;
    date: string;
    range: { from: string; to: string };
    previous: string;
    next: string;
    days: Record<string, number> | null;
    appointments: Appointment[];
    serviceTypes: { id: number; name: string }[];
};

const t = fr.appointments;

/** « 2026-10-27 » lu en heure locale. */
const parse = (day: string) => new Date(`${day}T12:00:00`);

const formatDay = (day: string, options: Intl.DateTimeFormatOptions) =>
    parse(day).toLocaleDateString('fr-DZ', options);

function title(view: View, date: string, range: Props['range']): string {
    switch (view) {
        case 'day':
            return formatDay(date, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            });
        case 'week':
            return `${formatDay(range.from, { day: 'numeric', month: 'short' })} – ${formatDay(range.to, { day: 'numeric', month: 'short' })}`;
        case 'month':
            return formatDay(date, { month: 'long', year: 'numeric' });
    }
}

/** Les jours de from à to (« 2026-10-26 »…). */
function daysBetween(from: string, to: string): string[] {
    const days: string[] = [];

    for (
        let day = parse(from);
        toIsoDate(day) <= to;
        day.setDate(day.getDate() + 1)
    ) {
        days.push(toIsoDate(day));
    }

    return days;
}

const visit = (view: View, date: string) =>
    appointmentsIndex.url({ query: { view, date } });

/** Grille du mois (lundi en premier) : nombre de RDV par jour, un clic ouvre le jour. */
function MonthGrid({
    range,
    days,
    today,
}: {
    range: Props['range'];
    days: Record<string, number>;
    today: string;
}) {
    const leading = (parse(range.from).getDay() + 6) % 7;

    return (
        <div className="rounded-xl border bg-card p-2 sm:p-3">
            <div className="grid grid-cols-7 gap-1 pb-1 text-center text-xs text-muted-foreground">
                {t.weekdays.map((weekday) => (
                    <div key={weekday}>{weekday}</div>
                ))}
            </div>
            <div className="grid grid-cols-7 gap-1">
                {Array.from({ length: leading }, (_, index) => (
                    <div key={`blank-${index}`} />
                ))}
                {daysBetween(range.from, range.to).map((day) => {
                    const count = days[day] ?? 0;

                    return (
                        <Link
                            key={day}
                            href={visit('day', day)}
                            aria-label={`${formatDay(day, { weekday: 'long', day: 'numeric', month: 'long' })} : ${t.count(count)}`}
                            className={cn(
                                'flex aspect-square flex-col items-center justify-start gap-1 rounded-md border p-1 text-sm hover:bg-accent sm:aspect-auto sm:min-h-20 sm:items-start sm:p-2',
                                day === today &&
                                    'border-primary font-semibold text-primary',
                                count === 0 && 'border-transparent',
                            )}
                        >
                            <span>{parse(day).getDate()}</span>
                            {count > 0 && (
                                <>
                                    <span
                                        className="size-2 rounded-full bg-primary sm:hidden"
                                        aria-hidden="true"
                                    />
                                    <span className="hidden rounded bg-primary/10 px-1.5 text-xs font-medium text-primary sm:inline">
                                        {t.count(count)}
                                    </span>
                                </>
                            )}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

export default function AppointmentsIndex({
    view,
    date,
    range,
    previous,
    next,
    days,
    appointments,
    serviceTypes,
    ...options
}: Props) {
    const today = toIsoDate(new Date());
    const [sheet, setSheet] = useState<{
        appointment: Appointment | null;
        needVehicle: boolean;
    } | null>(null);
    const [sheetCount, setSheetCount] = useState(0);

    const openSheet = (
        appointment: Appointment | null,
        needVehicle = false,
    ) => {
        setSheetCount((count) => count + 1);
        setSheet({ appointment, needVehicle });
    };

    const list = (items: Appointment[]) =>
        items.map((appointment) => (
            <AppointmentCard
                key={appointment.id}
                appointment={appointment}
                onEdit={openSheet}
            />
        ));

    return (
        <>
            <Head title={t.title} />
            <div className="space-y-4 p-4 md:p-6">
                <PageHeader
                    title={t.title}
                    description={t.description}
                    actions={
                        <Button
                            className="h-11"
                            onClick={() => openSheet(null)}
                        >
                            <Plus />
                            {t.add}
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <ToggleGroup
                        type="single"
                        value={view}
                        onValueChange={(value: View | '') =>
                            value && router.get(visit(value, date))
                        }
                        className="w-auto"
                    >
                        {(['day', 'week', 'month'] as const).map((key) => (
                            <ToggleGroupItem
                                key={key}
                                value={key}
                                className="h-11 rounded-md border border-input bg-background px-3 shadow-xs data-[state=on]:border-primary data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                            >
                                {t.views[key]}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>

                    <div className="inline-flex items-center gap-1 rounded-lg border bg-card p-1">
                        <Button asChild variant="ghost" size="icon-lg">
                            <Link
                                href={visit(view, previous)}
                                aria-label={t.previous}
                            >
                                <ChevronLeft />
                            </Link>
                        </Button>
                        <span className="min-w-40 text-center font-medium first-letter:uppercase">
                            {title(view, date, range)}
                        </span>
                        <Button asChild variant="ghost" size="icon-lg">
                            <Link href={visit(view, next)} aria-label={t.next}>
                                <ChevronRight />
                            </Link>
                        </Button>
                    </div>

                    {(today < range.from || today > range.to) && (
                        <Button asChild variant="outline" className="h-11">
                            <Link href={visit(view, today)}>{t.today}</Link>
                        </Button>
                    )}
                </div>

                {view === 'month' && days && (
                    <MonthGrid range={range} days={days} today={today} />
                )}

                {view === 'day' &&
                    (appointments.length === 0 ? (
                        <EmptyState
                            icon={CalendarClock}
                            title={t.empty}
                            description={t.emptyText}
                            action={
                                <Button
                                    className="h-11"
                                    onClick={() => openSheet(null)}
                                >
                                    <Plus />
                                    {t.add}
                                </Button>
                            }
                        />
                    ) : (
                        <div className="space-y-3">{list(appointments)}</div>
                    ))}

                {view === 'week' &&
                    daysBetween(range.from, range.to).map((day) => {
                        const items = appointments.filter((a) => a.day === day);

                        return (
                            <section key={day} className="space-y-2">
                                <h2
                                    className={cn(
                                        'font-semibold first-letter:uppercase',
                                        day === today && 'text-primary',
                                    )}
                                >
                                    {formatDay(day, {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    })}
                                </h2>
                                {items.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t.empty}
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {list(items)}
                                    </div>
                                )}
                            </section>
                        );
                    })}
            </div>

            <AppointmentSheet
                key={sheetCount}
                open={sheet !== null}
                onOpenChange={(open) => !open && setSheet(null)}
                appointment={sheet?.appointment ?? null}
                needVehicle={sheet?.needVehicle}
                date={view === 'day' ? date : today}
                serviceTypes={serviceTypes}
                options={options}
            />
        </>
    );
}
