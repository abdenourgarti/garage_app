import { useState } from 'react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { formatQuantity, parseDecimal } from '@/lib/format';
import { cn } from '@/lib/utils';

export type DecimalInputProps = Omit<
    ComponentProps<'input'>,
    'value' | 'onChange' | 'type' | 'defaultValue'
> & {
    /** Valeur brute envoyée au serveur : "12500.5" ou "". */
    value: string;
    onValueChange: (value: string) => void;
};

const ALLOWED = /^[\d\s  ,.]*$/;

/**
 * Saisie numérique à la française : clavier numérique sur mobile, séparateurs
 * de milliers hors focus, suffixe d'unité. `name` envoie la valeur brute.
 */
export function DecimalInput({
    value,
    onValueChange,
    suffix,
    name,
    className,
    onFocus,
    onBlur,
    ...props
}: DecimalInputProps & { suffix: string }) {
    const [draft, setDraft] = useState<string | null>(null);

    const display = draft ?? (value === '' ? '' : formatQuantity(value));

    return (
        <div className="relative">
            <Input
                {...props}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                value={display}
                className={cn('pe-10 text-end tabular-nums', className)}
                onFocus={(event) => {
                    setDraft(value.replace('.', ','));
                    onFocus?.(event);
                }}
                onChange={(event) => {
                    if (!ALLOWED.test(event.target.value)) {
                        return;
                    }

                    setDraft(event.target.value);
                    onValueChange(parseDecimal(event.target.value));
                }}
                onBlur={(event) => {
                    setDraft(null);
                    onBlur?.(event);
                }}
            />
            <span className="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-muted-foreground">
                {suffix}
            </span>
            {name && <input type="hidden" name={name} value={value} />}
        </div>
    );
}
