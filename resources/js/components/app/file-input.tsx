import { FileText, Paperclip, X } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { fr } from '@/lib/i18n/fr';

type Props = {
    id: string;
    value: File | null;
    onChange: (file: File | null) => void;
    /** Un fichier est déjà enregistré côté serveur (il sera remplacé si on en choisit un). */
    hasCurrent?: boolean;
    'aria-invalid'?: boolean;
};

/**
 * Pièce jointe facultative : image ou PDF (preuve de paiement, facture fournisseur…).
 * Bouton lisible plutôt que le champ natif du navigateur.
 */
export function FileInput({
    id,
    value,
    onChange,
    hasCurrent = false,
    ...props
}: Props) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <input
                ref={input}
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                className="sr-only"
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                aria-invalid={props['aria-invalid']}
            />
            <Button
                type="button"
                variant="outline"
                className="h-11"
                onClick={() => input.current?.click()}
            >
                <Paperclip />
                {value || hasCurrent ? fr.file.replace : fr.file.choose}
            </Button>
            {value ? (
                <span className="inline-flex min-w-0 items-center gap-1.5 text-sm">
                    <FileText
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span className="truncate">{value.name}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => {
                            onChange(null);

                            if (input.current) {
                                input.current.value = '';
                            }
                        }}
                        aria-label={fr.file.remove}
                    >
                        <X />
                    </Button>
                </span>
            ) : (
                <span className="text-sm text-muted-foreground">
                    {hasCurrent ? fr.file.current : fr.file.none}
                </span>
            )}
        </div>
    );
}
