import { DecimalInput } from '@/components/app/decimal-input';
import type { DecimalInputProps } from '@/components/app/decimal-input';

/** Quantité (litres de vrac, pièces) : unité affichée en suffixe, « L » par défaut. */
export function QuantityInput({
    unit = 'L',
    ...props
}: DecimalInputProps & { unit?: string }) {
    return <DecimalInput suffix={unit} {...props} />;
}
