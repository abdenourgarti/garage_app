import { Head, Link, router } from '@inertiajs/react';
import { BellRing, Check, Phone } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { ClientsNav } from '@/components/clients/clients-nav';
import { km, remainingLabel } from '@/components/clients/next-oil-change';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showClient } from '@/routes/clients';
import { call } from '@/routes/reminders';
import { show as showVehicle } from '@/routes/vehicles';
import type { ReminderRow } from '@/types/reminders';

const t = fr.reminders;
function Reminder({ reminder }: { reminder: ReminderRow }) {
    const [saving, setSaving] = useState(false);

    return (
        <li className="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1.3fr)_auto] md:items-center">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={showVehicle(reminder.id)}
                        className="font-mono font-semibold hover:underline"
                    >
                        {reminder.plate}
                    </Link>
                    <StatusBadge type="reminder" status={reminder.status} />
                </div>
                <div className="truncate text-sm text-muted-foreground">
                    {reminder.vehicle}
                </div>
            </div>
            <div className="min-w-0">
                <Link
                    href={showClient(reminder.client_id)}
                    className="block truncate font-medium hover:underline"
                >
                    {reminder.client}
                </Link>
                {reminder.called_at && (
                    <div className="text-sm text-muted-foreground">
                        {t.calledOn(formatDate(reminder.called_at))}
                    </div>
                )}
            </div>
            <div className="text-sm">
                {reminder.due.map((due) => (
                    <div key={due.type_id}>
                        <span className="font-medium">{due.type}</span>{' '}
                        {fr.vehicles.dueAt(km(due.due_km))} ·{' '}
                        <span
                            className={
                                due.status === 'overdue'
                                    ? 'text-destructive'
                                    : 'text-warning'
                            }
                        >
                            {remainingLabel(due.remaining_km)}
                        </span>
                    </div>
                ))}
                {reminder.estimated_km !== null && (
                    <div className="text-muted-foreground">
                        {t.estimated} : {km(reminder.estimated_km)}
                    </div>
                )}
            </div>
            <div className="flex flex-wrap gap-2 md:justify-end">
                {reminder.phone && (
                    <Button asChild className="h-11 flex-1 md:flex-none">
                        <a href={`tel:${reminder.phone.replace(/\s+/g, '')}`}>
                            <Phone />
                            {reminder.phone}
                        </a>
                    </Button>
                )}
                {!reminder.called && (
                    <Button
                        variant="outline"
                        className="h-11 flex-1 md:flex-none"
                        disabled={saving}
                        onClick={() =>
                            router.post(
                                call(reminder.id),
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setSaving(true),
                                    onFinish: () => setSaving(false),
                                },
                            )
                        }
                    >
                        <Check />
                        {t.markCalled}
                    </Button>
                )}
            </div>
        </li>
    );
}

export default function Reminders({
    reminders,
    threshold,
}: {
    reminders: ReminderRow[];
    threshold: number;
}) {
    const toCall = reminders.filter((reminder) => !reminder.called);
    const called = reminders.filter((reminder) => reminder.called);

    return (
        <>
            <Head title={t.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={fr.clients.title}
                    description={fr.clients.description}
                />
                <ClientsNav current="reminders" />
                <p className="text-sm text-muted-foreground">
                    {t.description(km(threshold))}
                </p>

                {toCall.length === 0 ? (
                    <EmptyState
                        icon={BellRing}
                        title={t.emptyTitle}
                        description={t.emptyText}
                    />
                ) : (
                    <ul className="space-y-3">
                        {toCall.map((reminder) => (
                            <Reminder key={reminder.id} reminder={reminder} />
                        ))}
                    </ul>
                )}

                {called.length > 0 && (
                    <details className="group space-y-3">
                        <summary className="flex min-h-11 cursor-pointer items-center font-semibold">
                            {t.called} ({called.length})
                        </summary>
                        <ul className="space-y-3 opacity-80">
                            {called.map((reminder) => (
                                <Reminder
                                    key={reminder.id}
                                    reminder={reminder}
                                />
                            ))}
                        </ul>
                    </details>
                )}
            </div>
        </>
    );
}
