import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

type Statuses = typeof fr.statuses;
type StatusType = keyof Statuses;
type Tone =
    | 'primary'
    | 'highlight'
    | 'success'
    | 'warning'
    | 'destructive'
    | 'muted';

const tones: { [T in StatusType]: Record<keyof Statuses[T], Tone> } = {
    serviceOrder: { en_cours: 'highlight', terminee: 'success' },
    invoice: {
        impayee: 'destructive',
        partielle: 'warning',
        payee: 'success',
        annulee: 'muted',
    },
    appointment: { prevu: 'primary', effectue: 'success', annule: 'muted' },
    stock: { ok: 'success', bas: 'warning', rupture: 'destructive' },
    purchase: {
        draft: 'muted',
        ordered: 'primary',
        received: 'success',
        cancelled: 'muted',
    },
};

const toneClasses: Record<Tone, { badge: string; dot: string }> = {
    primary: { badge: 'bg-primary/10 text-primary', dot: 'bg-primary' },
    highlight: {
        badge: 'bg-highlight/20 text-foreground',
        dot: 'bg-highlight',
    },
    success: { badge: 'bg-success/12 text-success', dot: 'bg-success' },
    warning: { badge: 'bg-warning/12 text-warning', dot: 'bg-warning' },
    destructive: {
        badge: 'bg-destructive/12 text-destructive',
        dot: 'bg-destructive',
    },
    muted: {
        badge: 'bg-muted text-muted-foreground',
        dot: 'bg-muted-foreground',
    },
};

type Props<T extends StatusType> = {
    type: T;
    status: keyof Statuses[T];
    className?: string;
};

/** Statut avec couleur + libellé français (jamais la couleur seule). */
export function StatusBadge<T extends StatusType>({
    type,
    status,
    className,
}: Props<T>) {
    const tone = toneClasses[tones[type][status] as Tone];
    const label = fr.statuses[type][status] as string;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                tone.badge,
                className,
            )}
        >
            <span
                className={cn('size-1.5 rounded-full', tone.dot)}
                aria-hidden="true"
            />
            {label}
        </span>
    );
}
