import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { CountUp } from '@/components/app/bits/count-up';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: number;
    /** 'money' : « 12 500 DA » ; 'number' : entier. */
    kind?: 'money' | 'number';
    icon: LucideIcon;
    /** Ligne secondaire : variation, rappel… */
    hint?: ReactNode;
    /** Bénéfice : vert si positif, rouge si négatif. */
    colorBySign?: boolean;
    className?: string;
};

/** Indicateur du tableau de bord (docs/04-design-system.md §4). */
export function StatCard({
    label,
    value,
    kind = 'number',
    icon: Icon,
    hint,
    colorBySign = false,
    className,
}: Props) {
    return (
        <div className={cn('rounded-xl border bg-card p-4', className)}>
            <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                <span>{label}</span>
                <Icon className="size-5" aria-hidden="true" />
            </div>
            <CountUp
                to={value}
                format={kind === 'money' ? formatMoney : undefined}
                className={cn(
                    'mt-2 block text-2xl font-semibold',
                    colorBySign && value > 0 && 'text-success',
                    colorBySign && value < 0 && 'text-destructive',
                )}
            />
            {hint && (
                <div className="mt-1 text-sm text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}
