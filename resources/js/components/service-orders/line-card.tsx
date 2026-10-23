import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import {
    Droplets,
    PackagePlus,
    ShoppingBag,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useRef } from 'react';
import { MoneyInput } from '@/components/app/money-input';
import InputError from '@/components/input-error';
import { PartRow } from '@/components/service-orders/part-row';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { duration, ease, prefersReducedMotion } from '@/lib/animations';
import { fr } from '@/lib/i18n/fr';
import { uid } from '@/lib/uid';
import type { LineData, PartRowData } from '@/types/service-orders';

gsap.registerPlugin(useGSAP);

type Props = {
    line: LineData;
    index: number;
    typeName: string;
    employees: { id: number; name: string }[];
    oilCapacity: string | null;
    onChange: (patch: Partial<LineData>) => void;
    onRemove: () => void;
    errors: Record<string, string | undefined>;
};

const t = fr.serviceOrders;

const newPart = (
    origin: PartRowData['origin'],
    isOil = false,
): PartRowData => ({
    key: uid(),
    origin,
    isOil,
    oil: null,
    article: null,
    designation: '',
    quantity: '1',
    unit_price: '',
    unit_cost: '',
});

/** Une ligne de travaux : mécanicien, main-d'œuvre et pièces. Apparition douce (≤ 200 ms). */
export function LineCard({
    line,
    index,
    typeName,
    employees,
    oilCapacity,
    onChange,
    onRemove,
    errors,
}: Props) {
    const card = useRef<HTMLElement>(null);

    useGSAP(
        () => {
            if (!prefersReducedMotion()) {
                gsap.from(card.current, {
                    opacity: 0,
                    y: 8,
                    duration: duration.base,
                    ease,
                });
            }
        },
        { scope: card },
    );

    const setPart = (key: number, patch: Partial<PartRowData>) =>
        onChange({
            parts: line.parts.map((part) =>
                part.key === key ? { ...part, ...patch } : part,
            ),
        });

    const addPart = (part: PartRowData) =>
        onChange({ parts: [...line.parts, part] });

    return (
        <section
            ref={card}
            className="grid gap-3 rounded-xl border bg-card p-3 sm:p-4"
            aria-label={typeName}
        >
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold">{typeName}</h3>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-lg"
                    className="text-destructive hover:text-destructive"
                    onClick={onRemove}
                    aria-label={`${t.removeLine} : ${typeName}`}
                >
                    <Trash2 />
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <label
                        htmlFor={`line-${line.key}-employee`}
                        className="text-sm font-medium"
                    >
                        {t.mechanic}
                    </label>
                    <Select
                        value={line.employee_id}
                        onValueChange={(value) =>
                            onChange({ employee_id: value })
                        }
                    >
                        <SelectTrigger
                            id={`line-${line.key}-employee`}
                            className="h-11 w-full"
                            aria-invalid={Boolean(
                                errors[`lines.${index}.employee_id`],
                            )}
                        >
                            <UserRound className="text-muted-foreground" />
                            <SelectValue placeholder={t.mechanicNone} />
                        </SelectTrigger>
                        <SelectContent>
                            {employees.map((employee) => (
                                <SelectItem
                                    key={employee.id}
                                    value={String(employee.id)}
                                >
                                    {employee.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={errors[`lines.${index}.employee_id`]}
                    />
                </div>
                <div className="grid gap-1.5">
                    <label
                        htmlFor={`line-${line.key}-labor`}
                        className="text-sm font-medium"
                    >
                        {t.labor}
                    </label>
                    <MoneyInput
                        id={`line-${line.key}-labor`}
                        value={line.labor_price}
                        onValueChange={(value) =>
                            onChange({ labor_price: value })
                        }
                        aria-invalid={Boolean(
                            errors[`lines.${index}.labor_price`],
                        )}
                    />
                    <InputError
                        message={errors[`lines.${index}.labor_price`]}
                    />
                </div>
            </div>

            {line.parts.length > 0 && (
                <ul className="grid gap-2" aria-label={t.parts}>
                    {line.parts.map((part, partIndex) => (
                        <PartRow
                            key={part.key}
                            part={part}
                            idPrefix={`line-${line.key}-part-${part.key}`}
                            onChange={(patch) => setPart(part.key, patch)}
                            onRemove={() =>
                                onChange({
                                    parts: line.parts.filter(
                                        (item) => item.key !== part.key,
                                    ),
                                })
                            }
                            oilCapacity={oilCapacity}
                            errors={(field) =>
                                errors[
                                    `lines.${index}.parts.${partIndex}.${field}`
                                ]
                            }
                        />
                    ))}
                </ul>
            )}

            <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <Button
                    type="button"
                    variant="outline"
                    className="h-11"
                    onClick={() => addPart(newPart('stock'))}
                >
                    <PackagePlus />
                    {t.addPart}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="h-11"
                    onClick={() => addPart(newPart('stock', true))}
                >
                    <Droplets className="text-highlight" />
                    {t.addOil}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="h-11"
                    onClick={() => addPart(newPart('client'))}
                >
                    <UserRound />
                    {t.addClientPart}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="h-11"
                    onClick={() => addPart(newPart('external'))}
                >
                    <ShoppingBag />
                    {t.addExternalPart}
                </Button>
            </div>
        </section>
    );
}
