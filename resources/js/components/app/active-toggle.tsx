import { useId } from 'react';
import { Switch } from '@/components/ui/switch';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

type Props = {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    className?: string;
};

/** Bascule Actif / Inactif des référentiels (un élément utilisé se désactive, il ne se supprime pas). */
export function ActiveToggle({
    checked,
    onCheckedChange,
    disabled,
    className,
}: Props) {
    const id = useId();

    return (
        <div
            className={cn('inline-flex min-h-11 items-center gap-2', className)}
        >
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onCheckedChange}
                disabled={disabled}
            />
            <label
                htmlFor={id}
                className={cn(
                    'w-14 cursor-pointer text-sm font-medium',
                    checked ? 'text-success' : 'text-muted-foreground',
                )}
            >
                {checked ? fr.common.active : fr.common.inactive}
            </label>
        </div>
    );
}
