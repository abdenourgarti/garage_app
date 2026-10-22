import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fr } from '@/lib/i18n/fr';
import { periodRange } from '@/lib/period';
import type { Period, PeriodPreset } from '@/lib/period';
import { cn } from '@/lib/utils';

type Props = {
    value: Period;
    onChange: (period: Period) => void;
    className?: string;
};

const presets: PeriodPreset[] = ['today', 'week', 'month', 'year', 'custom'];

/** Aujourd'hui / Semaine / Mois / Année / Du… au… (champs date natifs : simples et adaptés au mobile). */
export function PeriodPicker({ value, onChange, className }: Props) {
    return (
        <div className={cn('flex flex-col gap-3', className)}>
            <ToggleGroup
                type="single"
                value={value.preset}
                onValueChange={(preset: PeriodPreset | '') => {
                    if (preset === '' || preset === value.preset) {
                        return;
                    }

                    onChange(
                        preset === 'custom'
                            ? { ...value, preset }
                            : periodRange(preset),
                    );
                }}
                className="grid w-full grid-cols-3 gap-2 sm:flex sm:w-auto sm:flex-wrap"
            >
                {presets.map((preset) => (
                    <ToggleGroupItem
                        key={preset}
                        value={preset}
                        className="h-11 rounded-md border border-input bg-background px-3 shadow-xs data-[state=on]:border-primary data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                    >
                        {fr.period[preset]}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            {value.preset === 'custom' && (
                <div className="grid grid-cols-2 gap-3 sm:flex sm:items-end">
                    <div className="grid gap-1.5">
                        <Label htmlFor="period-from">{fr.period.from}</Label>
                        <Input
                            id="period-from"
                            type="date"
                            className="h-11"
                            value={value.from}
                            max={value.to}
                            onChange={(event) =>
                                onChange({ ...value, from: event.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="period-to">{fr.period.to}</Label>
                        <Input
                            id="period-to"
                            type="date"
                            className="h-11"
                            value={value.to}
                            min={value.from}
                            onChange={(event) =>
                                onChange({ ...value, to: event.target.value })
                            }
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
