import { useForm } from '@inertiajs/react';
import ArticleController from '@/actions/App/Http/Controllers/Stock/ArticleController';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
import { QuantityInput } from '@/components/app/quantity-input';
import { Button } from '@/components/ui/button';
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
import { nullable } from '@/types/clients';
import type { Option, StockOptions } from '@/types/stock';

export type EditableArticle = {
    id: number;
    name: string;
    brand: string | null;
    reference: string | null;
    article_category_id: number;
    stock_unit: string;
    purchase_price: string;
    sale_price: string;
    alert_threshold: string | null;
    oil_format: string | null;
};

type Props = StockOptions & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Absent : création d'une pièce. */
    article?: EditableArticle;
};

const t = fr.stock.fields;

const unitSymbol = (unit: string) => (unit === 'liter' ? 'L' : 'u');

/**
 * Pièce : tous les champs. Format d'huile : catégorie, prix et seuil seulement
 * (le reste vient de la fiche huile). Remonté à chaque ouverture (key).
 */
export function ArticleFormSheet({
    open,
    onOpenChange,
    article,
    categories,
    stockUnits,
}: Props) {
    const isOilFormat = Boolean(article?.oil_format);
    const form = useForm({
        name: article?.name ?? '',
        article_category_id: article ? String(article.article_category_id) : '',
        brand: article?.brand ?? '',
        reference: article?.reference ?? '',
        stock_unit: article?.stock_unit ?? 'unit',
        purchase_price: article?.purchase_price ?? '',
        sale_price: article?.sale_price ?? '',
        alert_threshold: article?.alert_threshold ?? '',
        initial_stock: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => {
            const payload = nullable(data);

            if (article) {
                delete payload.initial_stock;
            }

            return payload as typeof data;
        });
        form.submit(
            article
                ? ArticleController.update(article.id)
                : ArticleController.store(),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const select = (
        id: string,
        value: string,
        options: Option[],
        field: 'article_category_id' | 'stock_unit',
    ) => (
        <Select
            value={value}
            onValueChange={(next) => form.setData(field, next)}
        >
            <SelectTrigger
                id={id}
                className="h-11 w-full"
                aria-invalid={Boolean(form.errors[field])}
            >
                <SelectValue placeholder={fr.select.placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const unit = unitSymbol(form.data.stock_unit);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>
                            {article
                                ? fr.stock.article.edit
                                : fr.stock.article.create}
                        </SheetTitle>
                        <SheetDescription>
                            {article
                                ? article.name
                                : fr.stock.article.createDescription}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4 sm:grid-cols-2">
                        {!isOilFormat && (
                            <FormField
                                id="article-name"
                                label={t.name}
                                required
                                error={form.errors.name}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="article-name"
                                    className="h-11"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    aria-invalid={Boolean(form.errors.name)}
                                />
                            </FormField>
                        )}
                        <FormField
                            id="article-category"
                            label={t.category}
                            required
                            error={form.errors.article_category_id}
                        >
                            {select(
                                'article-category',
                                form.data.article_category_id,
                                categories.map((category) => ({
                                    value: String(category.id),
                                    label: category.name,
                                })),
                                'article_category_id',
                            )}
                        </FormField>
                        {!isOilFormat && (
                            <>
                                <FormField
                                    id="article-unit"
                                    label={t.stockUnit}
                                    required
                                    error={form.errors.stock_unit}
                                >
                                    {select(
                                        'article-unit',
                                        form.data.stock_unit,
                                        stockUnits,
                                        'stock_unit',
                                    )}
                                </FormField>
                                <FormField
                                    id="article-brand"
                                    label={t.brand}
                                    error={form.errors.brand}
                                >
                                    <Input
                                        id="article-brand"
                                        className="h-11"
                                        value={form.data.brand}
                                        onChange={(event) =>
                                            form.setData(
                                                'brand',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="article-reference"
                                    label={t.reference}
                                    error={form.errors.reference}
                                >
                                    <Input
                                        id="article-reference"
                                        className="h-11 uppercase"
                                        value={form.data.reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </>
                        )}
                        <FormField
                            id="article-purchase-price"
                            label={t.purchasePrice}
                            required
                            error={form.errors.purchase_price}
                        >
                            <MoneyInput
                                id="article-purchase-price"
                                value={form.data.purchase_price}
                                onValueChange={(value) =>
                                    form.setData('purchase_price', value)
                                }
                                aria-invalid={Boolean(
                                    form.errors.purchase_price,
                                )}
                            />
                        </FormField>
                        <FormField
                            id="article-sale-price"
                            label={t.salePrice}
                            required
                            error={form.errors.sale_price}
                        >
                            <MoneyInput
                                id="article-sale-price"
                                value={form.data.sale_price}
                                onValueChange={(value) =>
                                    form.setData('sale_price', value)
                                }
                                aria-invalid={Boolean(form.errors.sale_price)}
                            />
                        </FormField>
                        <FormField
                            id="article-threshold"
                            label={t.alertThreshold}
                            hint={t.alertThresholdHint}
                            error={form.errors.alert_threshold}
                        >
                            <QuantityInput
                                id="article-threshold"
                                unit={unit}
                                value={form.data.alert_threshold}
                                onValueChange={(value) =>
                                    form.setData('alert_threshold', value)
                                }
                            />
                        </FormField>
                        {!article && (
                            <FormField
                                id="article-initial"
                                label={t.initialStock}
                                hint={t.initialStockHint}
                                error={form.errors.initial_stock}
                            >
                                <QuantityInput
                                    id="article-initial"
                                    unit={unit}
                                    value={form.data.initial_stock}
                                    onValueChange={(value) =>
                                        form.setData('initial_stock', value)
                                    }
                                />
                            </FormField>
                        )}
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
