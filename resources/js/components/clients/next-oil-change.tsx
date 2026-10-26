import { StatusBadge } from '@/components/app/status-badge';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import type { DueItem, ReminderStatus } from '@/types/reminders';

const t = fr.vehicles;

export const km = (value: number) => formatQuantity(value, 'km');

/** « reste ~400 km » ou « dépassée de ~2 100 km ». */
export function remainingLabel(remaining: number | null): string {
    if (remaining === null) {
        return '';
    }

    return remaining > 0
        ? t.remaining(km(remaining))
        : t.overdueBy(km(-remaining));
}

export type VehicleReminder = {
    estimated_km: number | null;
    last_km_date: string | null;
    due: DueItem[];
    status: ReminderStatus;
};

/** Fiche véhicule : une ligne par entretien à rappel (vidange moteur, boîte, distribution…). */
export function NextOilChange({ reminder }: { reminder: VehicleReminder }) {
    return (
        <div className="space-y-3">
            {reminder.estimated_km !== null && (
                <p className="text-sm text-muted-foreground">
                    {t.estimatedKm(km(reminder.estimated_km))}
                </p>
            )}
            {reminder.due.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t.noReminder}</p>
            ) : (
                <ul className="space-y-2">
                    {reminder.due.map((due) => (
                        <li
                            key={due.type_id}
                            className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <div className="font-medium">{due.type}</div>
                                <div className="text-sm text-muted-foreground">
                                    {t.dueAt(km(due.due_km))}
                                    {due.remaining_km !== null && (
                                        <>
                                            {' · '}
                                            <span
                                                className={cn(
                                                    due.status === 'overdue' &&
                                                        'text-destructive',
                                                    due.status === 'soon' &&
                                                        'text-warning',
                                                )}
                                            >
                                                {remainingLabel(
                                                    due.remaining_km,
                                                )}
                                            </span>
                                        </>
                                    )}
                                </div>
                            </div>
                            <StatusBadge type="reminder" status={due.status} />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
