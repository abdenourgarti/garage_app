import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    /** Action principale (bouton) : en haut à droite sur PC, pleine largeur sur mobile. */
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({ title, description, actions, className }: Props) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0 space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                    {actions}
                </div>
            )}
        </div>
    );
}
