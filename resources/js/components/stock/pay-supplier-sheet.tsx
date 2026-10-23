import { useForm } from '@inertiajs/react';
import PurchasePaymentController from '@/actions/App/Http/Controllers/Stock/PurchasePaymentController';
import { FileInput } from '@/components/app/file-input';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
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
import { formatMoney, toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';

export type PaymentMethodOption = {
    id: number;
    name: string;
    is_cash: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: { id: number; remaining: string; label: string } | null;
    /** Adresse d’envoi ; par défaut, le paiement d’un achat. */
    url?: string;
    title?: string;
    submitLabel?: string;
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

const t = fr.payment;

/** Paiement (reste dû proposé) d’un achat ou d’une facture, avec preuve facultative. Remonté à chaque ouverture (key). */
export function PaySupplierSheet({
    open,
    onOpenChange,
    target,
    url,
    title = t.title,
    submitLabel = t.submit,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const today = toIsoDate(new Date());
    const form = useForm<{
        amount: string;
        paid_at: string;
        payment_method_id: string;
        proof: File | null;
    }>({
        amount: target?.remaining ?? '',
        paid_at: today,
        payment_method_id: defaultPaymentMethodId
            ? String(defaultPaymentMethodId)
            : '',
        proof: null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!target) {
            return;
        }

        form.post(url ?? PurchasePaymentController.store.url(target.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{title}</SheetTitle>
                        <SheetDescription>
                            {target?.label}
                            {target && (
                                <span className="block">
                                    {t.remaining(formatMoney(target.remaining))}
                                </span>
                            )}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        <FormField
                            id="payment-amount"
                            label={t.amount}
                            required
                            error={form.errors.amount}
                        >
                            <MoneyInput
                                id="payment-amount"
                                value={form.data.amount}
                                onValueChange={(value) =>
                                    form.setData('amount', value)
                                }
                                aria-invalid={Boolean(form.errors.amount)}
                            />
                        </FormField>
                        <FormField
                            id="payment-date"
                            label={t.date}
                            required
                            error={form.errors.paid_at}
                        >
                            <Input
                                id="payment-date"
                                type="date"
                                className="h-11"
                                max={today}
                                value={form.data.paid_at}
                                onChange={(event) =>
                                    form.setData('paid_at', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="payment-method"
                            label={t.method}
                            required
                            error={form.errors.payment_method_id}
                        >
                            <Select
                                value={form.data.payment_method_id}
                                onValueChange={(value) =>
                                    form.setData('payment_method_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="payment-method"
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
                        <FormField
                            id="payment-proof"
                            label={t.proof}
                            hint={t.proofHint}
                            error={form.errors.proof}
                        >
                            <FileInput
                                id="payment-proof"
                                value={form.data.proof}
                                onChange={(file) => form.setData('proof', file)}
                                aria-invalid={Boolean(form.errors.proof)}
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
                            {submitLabel}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
