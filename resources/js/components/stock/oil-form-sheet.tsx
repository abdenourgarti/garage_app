import { useForm } from '@inertiajs/react';
import OilProductController from '@/actions/App/Http/Controllers/Stock/OilProductController';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
import { QuantityInput } from '@/components/app/quantity-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';
import type { OilSheet, StockOptions } from '@/types/stock';

type FormatValues = {
    checked: boolean;
    purchase_price: string;
    sale_price: string;
    alert_threshold: string;
    initial_stock: string;
};

type Props = StockOptions & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Absent : nouvelle huile. */
    oil?: OilSheet & { article_category_id: number };
};

const t = fr.stock.oil;

const emptyFormat: FormatValues = {
    checked: false,
    purchase_price: '',
    sale_price: '',
    alert_threshold: '',
    initial_stock: '',
};

/**
 * Une huile = une fiche + des formats cochés (bidon 5 L, 4 L, 1 L, vrac), chacun avec ses prix.
 * En modification, on ajoute des formats ; les existants se modifient depuis leur fiche article.
 */
export function OilFormSheet({
    open,
    onOpenChange,
    oil,
    categories,
    oilUsages,
    oilFormats,
}: Props) {
    const existing = new Set(oil?.formats.map((format) => format.format) ?? []);
    const defaultCategory =
        oil?.article_category_id ??
        categories.find((category) => /huile/i.test(category.name))?.id;

    const form = useForm({
        brand: oil?.brand ?? '',
        name: oil?.name ?? '',
        viscosity: oil?.viscosity ?? '',
        usage: oil?.usage ?? 'moteur',
        approval: oil?.approval ?? '',
        article_category_id: defaultCategory ? String(defaultCategory) : '',
        formats: Object.fromEntries(
            oilFormats.map((format) => [format.value, emptyFormat]),
        ) as Record<string, FormatValues>,
    });

    const setFormat = (format: string, patch: Partial<FormatValues>) =>
        form.setData('formats', {
            ...form.data.formats,
            [format]: { ...form.data.formats[format], ...patch },
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform(
            (data) =>
                ({
                    ...data,
                    name: data.name || null,
                    approval: data.approval || null,
                    formats: Object.entries(data.formats)
                        .filter(
                            ([format, values]) =>
                                values.checked && !existing.has(format),
                        )
                        .map(([format, values]) => ({
                            format,
                            purchase_price: values.purchase_price,
                            sale_price: values.sale_price,
                            alert_threshold: values.alert_threshold || null,
                            initial_stock: values.initial_stock || null,
                        })),
                }) as never,
        );
        form.submit(
            oil
                ? OilProductController.update(oil.id)
                : OilProductController.store(),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const errors = form.errors as Record<string, string | undefined>;

    // Les erreurs des formats arrivent indexées dans l'ordre des formats envoyés (formats.0.sale_price…).
    const sentFormats = Object.entries(form.data.formats)
        .filter(([format, values]) => values.checked && !existing.has(format))
        .map(([format]) => format);
    const formatError = (format: string, field: string) =>
        errors[`formats.${sentFormats.indexOf(format)}.${field}`];

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{oil ? t.edit : t.create}</SheetTitle>
                        <SheetDescription>{t.description}</SheetDescription>
                    </SheetHeader>

                    <div className="grid gap-6 px-4 pb-4">
                        <section className="grid gap-4 sm:grid-cols-2">
                            <h3 className="font-semibold sm:col-span-2">
                                {t.sheet}
                            </h3>
                            <FormField
                                id="oil-brand"
                                label={t.brand}
                                required
                                error={errors.brand}
                            >
                                <Input
                                    id="oil-brand"
                                    className="h-11"
                                    value={form.data.brand}
                                    onChange={(event) =>
                                        form.setData(
                                            'brand',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.brand)}
                                />
                            </FormField>
                            <FormField
                                id="oil-name"
                                label={t.name}
                                error={errors.name}
                            >
                                <Input
                                    id="oil-name"
                                    className="h-11"
                                    placeholder={t.namePlaceholder}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="oil-viscosity"
                                label={t.viscosity}
                                required
                                error={errors.viscosity}
                            >
                                <Input
                                    id="oil-viscosity"
                                    className="h-11 uppercase"
                                    placeholder={t.viscosityPlaceholder}
                                    value={form.data.viscosity}
                                    onChange={(event) =>
                                        form.setData(
                                            'viscosity',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.viscosity)}
                                />
                            </FormField>
                            <FormField
                                id="oil-usage"
                                label={t.usage}
                                required
                                error={errors.usage}
                            >
                                <Select
                                    value={form.data.usage}
                                    onValueChange={(value) =>
                                        form.setData('usage', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="oil-usage"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {oilUsages.map((usage) => (
                                            <SelectItem
                                                key={usage.value}
                                                value={usage.value}
                                            >
                                                {usage.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                id="oil-approval"
                                label={t.approval}
                                error={errors.approval}
                            >
                                <Input
                                    id="oil-approval"
                                    className="h-11"
                                    placeholder={t.approvalPlaceholder}
                                    value={form.data.approval}
                                    onChange={(event) =>
                                        form.setData(
                                            'approval',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="oil-category"
                                label={fr.stock.fields.category}
                                required
                                error={errors.article_category_id}
                            >
                                <Select
                                    value={form.data.article_category_id}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'article_category_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="oil-category"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue
                                            placeholder={fr.select.placeholder}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem
                                                key={category.id}
                                                value={String(category.id)}
                                            >
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </section>

                        <section className="grid gap-3">
                            <div>
                                <h3 className="font-semibold">{t.formats}</h3>
                                <p className="text-sm text-muted-foreground">
                                    {t.formatsHint}
                                </p>
                                <InputError message={errors.formats} />
                            </div>
                            {oilFormats.map((format) => {
                                const values = form.data.formats[format.value];
                                const isExisting = existing.has(format.value);
                                const isBulk = format.value === 'bulk';
                                const unit = isBulk ? 'L' : 'u';

                                return (
                                    <div
                                        key={format.value}
                                        className="rounded-xl border bg-card p-3"
                                    >
                                        <label className="flex min-h-11 cursor-pointer items-center gap-3">
                                            <Checkbox
                                                id={`oil-format-${format.value}`}
                                                className="size-5"
                                                checked={
                                                    isExisting || values.checked
                                                }
                                                disabled={isExisting}
                                                onCheckedChange={(checked) =>
                                                    setFormat(format.value, {
                                                        checked:
                                                            checked === true,
                                                    })
                                                }
                                            />
                                            <span className="font-medium">
                                                {format.label}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                (
                                                {isBulk ? t.perLitre : t.perCan}
                                                )
                                            </span>
                                        </label>
                                        {isExisting && (
                                            <p className="ps-8 text-sm text-muted-foreground">
                                                {t.existingFormat}
                                            </p>
                                        )}
                                        {values.checked && !isExisting && (
                                            <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                                <FormField
                                                    id={`oil-${format.value}-purchase`}
                                                    label={
                                                        fr.stock.fields
                                                            .purchasePrice
                                                    }
                                                    required
                                                    error={formatError(
                                                        format.value,
                                                        'purchase_price',
                                                    )}
                                                >
                                                    <MoneyInput
                                                        id={`oil-${format.value}-purchase`}
                                                        value={
                                                            values.purchase_price
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setFormat(
                                                                format.value,
                                                                {
                                                                    purchase_price:
                                                                        value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    id={`oil-${format.value}-sale`}
                                                    label={
                                                        fr.stock.fields
                                                            .salePrice
                                                    }
                                                    required
                                                    error={formatError(
                                                        format.value,
                                                        'sale_price',
                                                    )}
                                                >
                                                    <MoneyInput
                                                        id={`oil-${format.value}-sale`}
                                                        value={
                                                            values.sale_price
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setFormat(
                                                                format.value,
                                                                {
                                                                    sale_price:
                                                                        value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    id={`oil-${format.value}-threshold`}
                                                    label={
                                                        fr.stock.fields
                                                            .alertThreshold
                                                    }
                                                    error={formatError(
                                                        format.value,
                                                        'alert_threshold',
                                                    )}
                                                >
                                                    <QuantityInput
                                                        id={`oil-${format.value}-threshold`}
                                                        unit={unit}
                                                        value={
                                                            values.alert_threshold
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setFormat(
                                                                format.value,
                                                                {
                                                                    alert_threshold:
                                                                        value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    id={`oil-${format.value}-initial`}
                                                    label={
                                                        fr.stock.fields
                                                            .initialStock
                                                    }
                                                    error={formatError(
                                                        format.value,
                                                        'initial_stock',
                                                    )}
                                                >
                                                    <QuantityInput
                                                        id={`oil-${format.value}-initial`}
                                                        unit={unit}
                                                        value={
                                                            values.initial_stock
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setFormat(
                                                                format.value,
                                                                {
                                                                    initial_stock:
                                                                        value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </section>
                    </div>

                    <SheetFooter className="mt-auto flex-row gap-2 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 flex-1"
                            onClick={() => onOpenChange(false)}
                        >
                            {fr.common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            className="h-11 flex-1"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {fr.common.save}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
