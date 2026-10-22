import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fr } from '@/lib/i18n/fr';

export default function Profile() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={fr.settings.profileTitle} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={fr.settings.profileTitle}
                    description={fr.settings.profileDescription}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{fr.settings.name}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={auth.user.name}
                                    required
                                    autoComplete="name"
                                    aria-invalid={!!errors.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="username">
                                    {fr.settings.username}
                                </Label>
                                <Input
                                    id="username"
                                    value={auth.user.username}
                                    disabled
                                    readOnly
                                />
                                <p className="text-sm text-muted-foreground">
                                    {fr.settings.usernameHint}
                                </p>
                            </div>

                            <div className="grid gap-1">
                                <span className="text-sm font-medium">
                                    {fr.settings.role}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {fr.user.roles[auth.user.role]}
                                </span>
                            </div>

                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                {fr.common.save}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
