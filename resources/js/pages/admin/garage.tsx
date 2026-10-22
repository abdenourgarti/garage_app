import { Head, useForm } from '@inertiajs/react';
import { ImageUp } from 'lucide-react';
import { useState } from 'react';
import GarageSettingsController from '@/actions/App/Http/Controllers/Admin/GarageSettingsController';
import { QuantityInput } from '@/components/app/quantity-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';

type Props = {
    settings: {
        name: string;
        address: string;
        phone: string;
        reminder_threshold_km: number;
    };
    logoUrl: string | null;
};

type GarageForm = {
    name: string;
    address: string;
    phone: string;
    reminder_threshold_km: string;
    logo: File | null;
    remove_logo: boolean;
};

const t = fr.admin.garageForm;

export default function Garage({ settings, logoUrl }: Props) {
    const form = useForm<GarageForm>({
        name: settings.name,
        address: settings.address,
        phone: settings.phone,
        reminder_threshold_km: String(settings.reminder_threshold_km),
        logo: null,
        remove_logo: false,
    });

    const [preview, setPreview] = useState(logoUrl);

    const chooseLogo = (file: File | null) => {
        form.setData('logo', file);
        setPreview(file ? URL.createObjectURL(file) : logoUrl);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        // PUT avec fichier : Inertia envoie un POST multipart + _method=put.
        form.submit(GarageSettingsController.update(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                form.setData((data) => ({
                    ...data,
                    logo: null,
                    remove_logo: false,
                }));
                setPreview((page.props as unknown as Props).logoUrl);
            },
        });
    };

    return (
        <>
            <Head title={fr.admin.garage} />

            <form onSubmit={submit} className="grid max-w-2xl gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="name">
                        {t.name}
                        <span className="text-destructive" aria-hidden="true">
                            {' '}
                            *
                        </span>
                    </Label>
                    <Input
                        id="name"
                        className="h-11"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                        aria-invalid={Boolean(form.errors.name)}
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="phone">{t.phone}</Label>
                        <Input
                            id="phone"
                            type="tel"
                            inputMode="tel"
                            className="h-11"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.phone)}
                        />
                        <InputError message={form.errors.phone} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="address">{t.address}</Label>
                        <Input
                            id="address"
                            className="h-11"
                            value={form.data.address}
                            onChange={(event) =>
                                form.setData('address', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.address)}
                        />
                        <InputError message={form.errors.address} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="logo">{t.logo}</Label>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-card">
                            {preview && !form.data.remove_logo ? (
                                <img
                                    src={preview}
                                    alt={t.logo}
                                    className="size-full object-contain"
                                />
                            ) : (
                                <ImageUp
                                    className="size-8 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                className="h-11 pt-2"
                                onChange={(event) =>
                                    chooseLogo(event.target.files?.[0] ?? null)
                                }
                                aria-invalid={Boolean(form.errors.logo)}
                            />
                            <p className="text-sm text-muted-foreground">
                                {t.logoHint}
                            </p>
                            {logoUrl && !form.data.logo && (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remove_logo"
                                        checked={form.data.remove_logo}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'remove_logo',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="remove_logo">
                                        {t.removeLogo}
                                    </Label>
                                </div>
                            )}
                        </div>
                    </div>
                    <InputError message={form.errors.logo} />
                </div>

                <div className="grid gap-2 sm:max-w-xs">
                    <Label htmlFor="reminder_threshold_km">
                        {t.reminderThreshold}
                        <span className="text-destructive" aria-hidden="true">
                            {' '}
                            *
                        </span>
                    </Label>
                    <QuantityInput
                        id="reminder_threshold_km"
                        unit="km"
                        value={form.data.reminder_threshold_km}
                        onValueChange={(value) =>
                            form.setData('reminder_threshold_km', value)
                        }
                        aria-invalid={Boolean(
                            form.errors.reminder_threshold_km,
                        )}
                    />
                    <p className="text-sm text-muted-foreground">
                        {t.reminderThresholdHint}
                    </p>
                    <InputError message={form.errors.reminder_threshold_km} />
                </div>

                <div>
                    <Button
                        type="submit"
                        className="h-11"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        {fr.common.save}
                    </Button>
                </div>
            </form>
        </>
    );
}
