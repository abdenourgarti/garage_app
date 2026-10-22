import { DecimalInput } from '@/components/app/decimal-input';
import type { DecimalInputProps } from '@/components/app/decimal-input';

/** Montant en dinars : « 12 500 » + suffixe DA. */
export function MoneyInput(props: DecimalInputProps) {
    return <DecimalInput suffix="DA" {...props} />;
}
