import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
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
import { Textarea } from '@/components/ui/textarea';
import { toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import expenses from '@/routes/cash/expenses';
import incomes from '@/routes/cash/incomes';

/** Mouvement saisi à la main à modifier (null : nouveau). */
export type ManualEntry = {
    id: number;
    label: string;
    amount: string;
    day: string;
    method_id: number | null;
    category_id: number | null;
    notes: string | null;
};

const t = fr.cash;

/** Dépense ou autre entrée saisie à la main. Remonté à chaque ouverture (key). */
export function EntrySheet({
    kind,
    entry,
    open,
    onOpenChange,
    categories,
    paymentMethods,
    defaultPaymentMethodId,
}: {
    kind: 'expense' | 'income';
    entry: ManualEntry | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: { id: number; name: string }[];
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
}) {
    const today = toIsoDate(new Date());
    const expense = kind === 'expense';
    const form = useForm({
        expense_category_id: entry?.category_id
            ? String(entry.category_id)
            : '',
        label: entry?.label ?? '',
        amount: entry?.amount ?? '',
        date: entry?.day ?? today,
        payment_method_id: String(
            entry?.method_id ?? defaultPaymentMethodId ?? '',
        ),
        notes: entry?.notes ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) =>
            expense
                ? {
                      expense_category_id: data.expense_category_id,
                      label: data.label,
                      amount: data.amount,
                      spent_at: data.date,
                      payment_method_id: data.payment_method_id,
                      notes: data.notes || null,
                  }
                : {
                      label: data.label,
                      amount: data.amount,
                      received_at: data.date,
                      payment_method_id: data.payment_method_id,
                      notes: data.notes || null,
                  },
        );

        const routes = expense ? expenses : incomes;
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (entry) {
            form.put(routes.update.url(entry.id), options);
        } else {
            form.post(routes.store.url(), options);
        }
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>
                            {expense
                                ? entry
                                    ? t.expenseEdit
                                    : t.expenseTitle
                                : entry
                                  ? t.incomeEdit
                                  : t.incomeTitle}
                        </SheetTitle>
                        <SheetDescription>
                            {expense ? t.expenseHint : t.incomeHint}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        {expense && (
                            <FormField
                                id="entry-category"
                                label={t.fields.category}
                                required
                                error={errors.expense_category_id}
                            >
                                <Select
                                    value={form.data.expense_category_id}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'expense_category_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="entry-category"
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
                        )}
                        <FormField
                            id="entry-label"
                            label={t.fields.label}
                            required
                            error={errors.label}
                        >
                            <Input
                                id="entry-label"
                                className="h-11"
                                placeholder={
                                    expense
                                        ? t.fields.labelExpense
                                        : t.fields.labelIncome
                                }
                                value={form.data.label}
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                                aria-invalid={Boolean(errors.label)}
                            />
                        </FormField>
                        <FormField
                            id="entry-amount"
                            label={t.fields.amount}
                            required
                            error={errors.amount}
                        >
                            <MoneyInput
                                id="entry-amount"
                                value={form.data.amount}
                                onValueChange={(value) =>
                                    form.setData('amount', value)
                                }
                                aria-invalid={Boolean(errors.amount)}
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-3">
                            <FormField
                                id="entry-date"
                                label={t.fields.date}
                                required
                                error={errors.spent_at ?? errors.received_at}
                            >
                                <Input
                                    id="entry-date"
                                    type="date"
                                    className="h-11"
                                    max={today}
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData('date', event.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="entry-method"
                                label={t.fields.method}
                                required
                                error={errors.payment_method_id}
                            >
                                <Select
                                    value={form.data.payment_method_id}
                                    onValueChange={(value) =>
                                        form.setData('payment_method_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="entry-method"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {paymentMethods.map((method) => (
                                            <SelectItem
                                                key={method.id}
                                                value={String(method.id)}
                                            >
                                                {method.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                        <FormField
                            id="entry-notes"
                            label={t.fields.notes}
                            error={errors.notes}
                        >
                            <Textarea
                                id="entry-notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </FormField>
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
