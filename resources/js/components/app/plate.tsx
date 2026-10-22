import { cn } from '@/lib/utils';

type Props = {
    plate: string;
    size?: 'sm' | 'lg';
    className?: string;
};

/**
 * Matricule affiché comme une plaque : c'est l'identifiant que le garagiste lit
 * sur la voiture, il doit se reconnaître au premier coup d'œil.
 */
export function Plate({ plate, size = 'sm', className }: Props) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md border-2 border-foreground/80 bg-white font-semibold tracking-wide whitespace-nowrap text-neutral-950 tabular-nums',
                size === 'sm'
                    ? 'px-2 py-0.5 text-sm'
                    : 'px-4 py-1.5 text-3xl sm:text-4xl',
                className,
            )}
        >
            {plate}
        </span>
    );
}
