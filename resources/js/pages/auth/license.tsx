import { Head, useForm } from '@inertiajs/react';
import { Check, Copy, KeyRound } from 'lucide-react';
import { useState } from 'react';
import LicenseController from '@/actions/App/Http/Controllers/LicenseController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';

const t = fr.license;

/** Activation hors ligne : identifiant de ce PC à envoyer, clé reçue à coller ou dicter. */
export default function License({
    machineId,
    hasKey,
}: {
    machineId: string;
    hasKey: boolean;
}) {
    const [copied, setCopied] = useState(false);
    const form = useForm({ key: '' });

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(machineId);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // Presse-papiers indisponible (http) : l'identifiant reste lisible et sélectionnable.
        }
    };

    return (
        <>
            <Head title={t.title} />
            <div className="grid gap-6">
                <div className="grid gap-1 text-center">
                    <h1 className="text-xl font-semibold">{t.title}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t.description}
                    </p>
                </div>

                {hasKey && (
                    <p className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                        {t.invalidStored}
                    </p>
                )}

                <div className="grid gap-2">
                    <Label>{t.machine}</Label>
                    <div className="flex items-center gap-2">
                        <code className="flex h-11 flex-1 items-center justify-center rounded-md border bg-muted font-mono text-sm tracking-wide whitespace-nowrap select-all sm:text-lg sm:tracking-wider">
                            {machineId}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            onClick={copy}
                        >
                            {copied ? <Check /> : <Copy />}
                            {copied ? t.copied : t.copy}
                        </Button>
                    </div>
                </div>

                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(LicenseController.activate());
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="license-key">{t.key}</Label>
                        <Input
                            id="license-key"
                            className="h-12 text-center font-mono text-lg tracking-wider uppercase"
                            placeholder={t.keyPlaceholder}
                            autoComplete="off"
                            autoCapitalize="characters"
                            spellCheck={false}
                            value={form.data.key}
                            onChange={(event) =>
                                form.setData('key', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.key)}
                        />
                        <InputError message={form.errors.key} />
                    </div>
                    <Button
                        type="submit"
                        className="h-11"
                        disabled={form.processing || !form.data.key.trim()}
                    >
                        {form.processing ? <Spinner /> : <KeyRound />}
                        {t.activate}
                    </Button>
                </form>
            </div>
        </>
    );
}
