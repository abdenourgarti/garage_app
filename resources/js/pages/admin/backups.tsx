import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    DatabaseBackup,
    Download,
    HardDrive,
    History,
} from 'lucide-react';
import { useState } from 'react';
import BackupController from '@/actions/App/Http/Controllers/Admin/BackupController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatTime } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

type Backup = { name: string; size: number; created_at: string };

type Props = {
    backups: Backup[];
    directory: string;
    secondary: string | null;
    secondaryError: string | null;
    keepDays: number;
    confirmation: string;
};

const t = fr.backups;

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`
        : `${Math.max(1, Math.round(bytes / 1024))} Ko`;
}

const when = (iso: string) => `${formatDate(iso)} ${formatTime(iso)}`;

/** Étape 2 de la restauration : taper le mot de confirmation. */
function RestoreConfirm({
    backup,
    word,
    open,
    onClose,
}: {
    backup: Backup;
    word: string;
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({ confirmation: '' });

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => !value && !form.processing && onClose()}
        >
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(BackupController.restore(backup.name));
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>{t.confirmTitle}</DialogTitle>
                        <DialogDescription>
                            {t.confirmText(word)}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Input
                            aria-label={t.confirmLabel}
                            className="h-11 font-mono tracking-widest uppercase"
                            autoComplete="off"
                            autoFocus
                            value={form.data.confirmation}
                            onChange={(event) =>
                                form.setData('confirmation', event.target.value)
                            }
                            disabled={form.processing}
                        />
                        <InputError message={form.errors.confirmation} />
                        {form.processing && (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner /> {t.restoring}
                            </p>
                        )}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            onClick={onClose}
                            disabled={form.processing}
                        >
                            {fr.common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            className="h-11"
                            disabled={
                                form.processing ||
                                form.data.confirmation.trim().toUpperCase() !==
                                    word
                            }
                        >
                            {t.confirmButton}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Backups({
    backups,
    directory,
    secondary,
    secondaryError,
    keepDays,
    confirmation,
}: Props) {
    const [saving, setSaving] = useState(false);
    // Dialogues toujours montés, pilotés par l'étape : 1 avertissement, 2 mot à taper.
    const [target, setTarget] = useState<Backup | null>(null);
    const [stage, setStage] = useState<'none' | 'warn' | 'type'>('none');
    const [restoreCount, setRestoreCount] = useState(0);
    const settings = useForm({ secondary: secondary ?? '' });
    const latest = backups[0];
    const stale =
        !latest ||
        Date.now() - new Date(latest.created_at).getTime() > 24 * 3600 * 1000;

    const backupNow = () =>
        router.post(
            BackupController.store.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    return (
        <>
            <Head title={fr.admin.backups} />
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <section className="space-y-4">
                    <div
                        className={cn(
                            'flex flex-col gap-4 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between',
                            stale && 'border-warning/50',
                        )}
                    >
                        <div className="flex items-start gap-3">
                            {stale ? (
                                <AlertTriangle className="mt-0.5 size-5 shrink-0 text-warning" />
                            ) : (
                                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" />
                            )}
                            <div>
                                <p className="font-semibold">
                                    {t.last} :{' '}
                                    {latest ? when(latest.created_at) : '—'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {!latest
                                        ? t.none
                                        : stale
                                          ? t.old
                                          : t.description}
                                </p>
                            </div>
                        </div>
                        <Button
                            className="h-11 shrink-0"
                            onClick={backupNow}
                            disabled={saving}
                        >
                            {saving ? <Spinner /> : <DatabaseBackup />}
                            {t.now}
                        </Button>
                    </div>

                    <div className="rounded-xl border bg-card">
                        <div className="border-b p-4">
                            <h2 className="font-semibold">{t.list}</h2>
                            <p className="text-sm text-muted-foreground">
                                {t.keep(keepDays)} {t.folder(directory)}
                            </p>
                        </div>
                        {backups.length === 0 ? (
                            <div className="p-4">
                                <EmptyState icon={History} title={t.none} />
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {backups.map((backup) => (
                                    <li
                                        key={backup.name}
                                        className="flex flex-wrap items-center gap-x-4 gap-y-2 p-4"
                                    >
                                        <span className="grid min-w-0 flex-1">
                                            <span className="font-medium tabular-nums">
                                                {when(backup.created_at)}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {backup.name.includes(
                                                    'avant-restauration',
                                                )
                                                    ? `${t.safety} · `
                                                    : ''}
                                                {formatSize(backup.size)}
                                            </span>
                                        </span>
                                        <span className="flex gap-2">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                className="h-11"
                                            >
                                                <a
                                                    href={BackupController.download.url(
                                                        backup.name,
                                                    )}
                                                    aria-label={`${t.download} ${backup.name}`}
                                                >
                                                    <Download />
                                                    <span className="hidden sm:inline">
                                                        {t.download}
                                                    </span>
                                                </a>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="h-11"
                                                onClick={() => {
                                                    setTarget(backup);
                                                    setRestoreCount(
                                                        (count) => count + 1,
                                                    );
                                                    setStage('warn');
                                                }}
                                            >
                                                {t.restore}
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <aside>
                    <form
                        className="space-y-3 rounded-xl border bg-card p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            settings.submit(BackupController.updateSettings(), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <h2 className="flex items-center gap-2 font-semibold">
                            <HardDrive className="size-4" />
                            {t.secondary}
                        </h2>
                        <FormField
                            id="backup-secondary"
                            label={t.secondary}
                            hint={t.secondaryHint}
                            error={
                                settings.errors.secondary ??
                                secondaryError ??
                                undefined
                            }
                            className="[&>label]:sr-only"
                        >
                            <Input
                                id="backup-secondary"
                                className="h-11 font-mono"
                                placeholder="D:\GarageApp-sauvegardes"
                                value={settings.data.secondary}
                                onChange={(event) =>
                                    settings.setData(
                                        'secondary',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        {secondary && !secondaryError && (
                            <p className="flex items-center gap-1.5 text-sm text-success">
                                <CheckCircle2 className="size-4" />
                                {t.secondaryOk}
                            </p>
                        )}
                        <Button
                            type="submit"
                            variant="outline"
                            className="h-11 w-full"
                            disabled={settings.processing}
                        >
                            {settings.processing && <Spinner />}
                            {t.secondarySave}
                        </Button>
                    </form>
                </aside>
            </div>

            <ConfirmDialog
                open={stage === 'warn'}
                onOpenChange={(open) => !open && setStage('none')}
                title={target ? t.restoreTitle(when(target.created_at)) : ''}
                description={t.restoreText}
                confirmLabel={t.restoreContinue}
                destructive
                onConfirm={() => setStage('type')}
            />
            {target && (
                <RestoreConfirm
                    key={`${target.name}-${restoreCount}`}
                    backup={target}
                    word={confirmation}
                    open={stage === 'type'}
                    onClose={() => setStage('none')}
                />
            )}
        </>
    );
}
