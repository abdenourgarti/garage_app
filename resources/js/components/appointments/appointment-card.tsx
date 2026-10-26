import { Link, router } from '@inertiajs/react';
import { Ban, Pencil, Phone, Play } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { cancel, start } from '@/routes/appointments';
import { show as showOrder } from '@/routes/service-orders';
import type { Appointment } from '@/types/appointments';

const t = fr.appointments;

/** Un rendez-vous : heure, client (appel), véhicule, prestations prévues, actions tant qu'il est Prévu. */
export function AppointmentCard({
    appointment,
    onEdit,
}: {
    appointment: Appointment;
    /** Modifier ; avec needVehicle quand « Commencer » manque du véhicule. */
    onEdit: (appointment: Appointment, needVehicle?: boolean) => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const [starting, setStarting] = useState(false);
    const planned = appointment.status === 'prevu';
    const phone = appointment.client.description;

    const begin = () => {
        if (!appointment.vehicle) {
            onEdit(appointment, true);

            return;
        }

        router.post(
            start(appointment.id),
            {},
            {
                onStart: () => setStarting(true),
                onFinish: () => setStarting(false),
            },
        );
    };

    return (
        <article
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-start',
                appointment.status === 'annule' && 'opacity-70',
            )}
        >
            <div className="w-14 shrink-0 text-lg font-semibold tabular-nums">
                {appointment.time}
            </div>
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">
                        {appointment.client.label}
                    </span>
                    <StatusBadge
                        type="appointment"
                        status={appointment.status}
                    />
                </div>
                <div className="text-sm text-muted-foreground">
                    {appointment.vehicle ? (
                        <>
                            <span className="font-mono font-semibold text-foreground">
                                {appointment.vehicle.label}
                            </span>{' '}
                            · {appointment.vehicle.description}
                        </>
                    ) : (
                        t.noVehicle
                    )}
                </div>
                {phone && (
                    <a
                        href={`tel:${phone.replace(/\s+/g, '')}`}
                        className="inline-flex min-h-11 items-center gap-1.5 text-sm text-primary underline-offset-4 hover:underline sm:min-h-0"
                        aria-label={t.call(phone)}
                    >
                        <Phone className="size-4" aria-hidden="true" />
                        {phone}
                    </a>
                )}
                {appointment.services.length > 0 && (
                    <p className="text-sm">
                        {appointment.services.map((s) => s.name).join(' · ')}
                    </p>
                )}
                {appointment.notes && (
                    <p className="text-sm text-muted-foreground italic">
                        « {appointment.notes} »
                    </p>
                )}
            </div>
            <div className="flex flex-wrap gap-2 sm:flex-col sm:items-stretch">
                {planned && (
                    <>
                        <Button
                            className="h-11 flex-1 sm:flex-none"
                            onClick={begin}
                            disabled={starting}
                        >
                            <Play />
                            {t.start}
                        </Button>
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() => onEdit(appointment)}
                        >
                            <Pencil />
                            {t.edit}
                        </Button>
                        <Button
                            variant="ghost"
                            className="h-11"
                            onClick={() => setConfirming(true)}
                        >
                            <Ban />
                            {t.cancel}
                        </Button>
                    </>
                )}
                {appointment.order && (
                    <Button asChild variant="outline" className="h-11">
                        <Link href={showOrder(appointment.order.id)}>
                            {appointment.order.number}
                        </Link>
                    </Button>
                )}
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t.cancelTitle}
                description={t.cancelText}
                confirmLabel={t.cancelConfirm}
                destructive
                onConfirm={() =>
                    router.post(
                        cancel(appointment.id),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setConfirming(false),
                        },
                    )
                }
            />
        </article>
    );
}
