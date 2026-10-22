import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: fr.settings.themes.light },
        { value: 'dark', icon: Moon, label: fr.settings.themes.dark },
        { value: 'system', icon: Monitor, label: fr.settings.themes.system },
    ];

    return (
        <div
            role="radiogroup"
            aria-label={fr.settings.appearanceTitle}
            className={cn(
                'inline-flex flex-wrap gap-1 rounded-lg bg-muted p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-h-11 items-center gap-2 rounded-md px-4 text-sm transition-colors',
                        appearance === value
                            ? 'bg-card font-medium shadow-xs'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                    )}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}
