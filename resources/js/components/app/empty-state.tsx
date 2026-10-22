import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    title: string;
    description?: string;
    /** Bouton d'action : un état vide invite toujours à agir. */
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center',
                className,
            )}
        >
            <span className="flex size-14 items-center justify-center rounded-full bg-secondary text-secondary-foreground">
                <Icon className="size-7" aria-hidden="true" />
            </span>
            <div className="max-w-sm space-y-1">
                <p className="text-base font-semibold">{title}</p>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action && <div className="pt-2">{action}</div>}
        </div>
    );
}
