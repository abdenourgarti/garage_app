import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';
import { store } from '@/routes/login';

export default function Login({ status }: { status?: string }) {
    return (
        <>
            <Head title={fr.auth.loginTitle} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="username">{fr.auth.username}</Label>
                            <Input
                                id="username"
                                name="username"
                                required
                                autoFocus
                                autoComplete="username"
                                autoCapitalize="none"
                                spellCheck={false}
                                className="h-11"
                                aria-invalid={!!errors.username}
                            />
                            <InputError message={errors.username} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">{fr.auth.password}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                className="h-11"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                className="size-5"
                            />
                            <Label htmlFor="remember">{fr.auth.remember}</Label>
                        </div>

                        <Button
                            type="submit"
                            className="h-11 w-full text-base"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {fr.auth.submit}
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            {fr.auth.forgotHint}
                        </p>
                    </>
                )}
            </Form>

            {status && (
                <div className="mt-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: fr.auth.loginTitle,
    description: fr.auth.loginDescription,
};
