import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    value: number | string | null | undefined;
    /** Vert si positif, rouge si négatif (bénéfice, solde). */
    colorBySign?: boolean;
    className?: string;
};

export function MoneyText({ value, colorBySign = false, className }: Props) {
    const number = Number(value);

    return (
        <span
            className={cn(
                'font-semibold whitespace-nowrap tabular-nums',
                colorBySign && number > 0 && 'text-success',
                colorBySign && number < 0 && 'text-destructive',
                className,
            )}
        >
            {formatMoney(value)}
        </span>
    );
}
