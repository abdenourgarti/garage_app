import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { fr } from '@/lib/i18n/fr';

type Props = {
    /** Bouton qui ouvre le dialogue (sinon piloter avec open / onOpenChange). */
    trigger?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    /** Action irréversible : bouton rouge. */
    destructive?: boolean;
    /** Motif obligatoire (ex. annulation de facture). */
    requireReason?: boolean;
    processing?: boolean;
    onConfirm: (reason: string) => void;
};

/** Confirmation réservée aux actions irréversibles (docs/04-design-system.md §1.8). */
export function ConfirmDialog({
    trigger,
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    destructive = false,
    requireReason = false,
    processing = false,
    onConfirm,
}: Props) {
    const [reason, setReason] = useState('');
    const canConfirm = !processing && (!requireReason || reason.trim() !== '');

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    setReason('');
                }

                onOpenChange?.(next);
            }}
        >
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (canConfirm) {
                            onConfirm(reason.trim());
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>

                    {requireReason && (
                        <div className="grid gap-2">
                            <Label htmlFor="confirm-reason">
                                {fr.confirm.reason}{' '}
                                <span className="text-muted-foreground">
                                    ({fr.common.required})
                                </span>
                            </Label>
                            <Textarea
                                id="confirm-reason"
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                placeholder={fr.confirm.reasonPlaceholder}
                                required
                                autoFocus
                            />
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11"
                            >
                                {fr.common.cancel}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            className="h-11"
                            disabled={!canConfirm}
                        >
                            {processing && <Spinner />}
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
