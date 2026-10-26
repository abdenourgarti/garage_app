import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ActiveToggle } from '@/components/app/active-toggle';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import PasswordInput from '@/components/password-input';
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
import type { Option } from '@/types/stock';

type UserRow = {
    id: number;
    name: string;
    username: string;
    role: 'gerant' | 'employe';
    is_active: boolean;
    must_change_password: boolean;
    is_me: boolean;
};

type Mode = { kind: 'new' } | { kind: 'edit' | 'password'; user: UserRow };

const t = fr.users;

function UserSheet({
    mode,
    roles,
    open,
    onClose,
}: {
    mode: Mode;
    roles: Option[];
    open: boolean;
    onClose: () => void;
}) {
    const user = mode.kind === 'new' ? null : mode.user;
    const form = useForm({
        name: user?.name ?? '',
        username: user?.username ?? '',
        role: user?.role ?? 'employe',
        password: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (mode.kind === 'new') {
            form.submit(UserController.store(), options);
        } else if (mode.kind === 'password') {
            form.transform((data) => ({ password: data.password }) as never);
            form.submit(UserController.resetPassword(mode.user.id), options);
        } else {
            form.transform(
                (data) =>
                    ({
                        name: data.name,
                        username: data.username,
                        role: data.role,
                    }) as never,
            );
            form.submit(UserController.update(mode.user.id), options);
        }
    };

    const title =
        mode.kind === 'new'
            ? t.create
            : mode.kind === 'edit'
              ? t.edit
              : t.resetTitle(mode.user.name);

    return (
        <Sheet open={open} onOpenChange={(value) => !value && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{title}</SheetTitle>
                        {mode.kind !== 'edit' && (
                            <SheetDescription>
                                {t.passwordHint}
                            </SheetDescription>
                        )}
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        {mode.kind !== 'password' && (
                            <>
                                <FormField
                                    id="user-name"
                                    label={t.name}
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        id="user-name"
                                        className="h-11"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.name)}
                                    />
                                </FormField>
                                <FormField
                                    id="user-username"
                                    label={t.username}
                                    hint={t.usernameHint}
                                    required
                                    error={form.errors.username}
                                >
                                    <Input
                                        id="user-username"
                                        className="h-11"
                                        autoCapitalize="none"
                                        autoComplete="off"
                                        spellCheck={false}
                                        value={form.data.username}
                                        onChange={(event) =>
                                            form.setData(
                                                'username',
                                                event.target.value.toLowerCase(),
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            form.errors.username,
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    id="user-role"
                                    label={t.role}
                                    required
                                    error={form.errors.role}
                                >
                                    <Select
                                        value={form.data.role}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'role',
                                                value as UserRow['role'],
                                            )
                                        }
                                        disabled={user?.is_me}
                                    >
                                        <SelectTrigger
                                            id="user-role"
                                            className="h-11 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            </>
                        )}
                        {mode.kind !== 'edit' && (
                            <FormField
                                id="user-password"
                                label={t.password}
                                required
                                error={form.errors.password}
                            >
                                <PasswordInput
                                    id="user-password"
                                    className="h-11"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
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
                            onClick={onClose}
                        >
                            {fr.common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            className="h-11 flex-1"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {mode.kind === 'password'
                                ? t.resetSave
                                : fr.common.save}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function UsersIndex({
    rows,
    roles,
}: {
    rows: Paginated<UserRow>;
    roles: Option[];
}) {
    // Panneau toujours monté, piloté par `open` (monté déjà ouvert, il se refermait au clic qui l'ouvrait).
    const [mode, setMode] = useState<Mode>({ kind: 'new' });
    const [sheetOpen, setSheetOpen] = useState(false);
    const [openCount, setOpenCount] = useState(0);

    const open = (next: Mode) => {
        setOpenCount((count) => count + 1);
        setMode(next);
        setSheetOpen(true);
    };

    const roleLabel = (value: string) =>
        roles.find((role) => role.value === value)?.label ?? value;

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: t.name,
            cell: (row) => (
                <span className="grid">
                    <span>
                        {row.name}
                        {row.is_me && (
                            <span className="ms-2 text-xs font-normal text-muted-foreground">
                                ({t.me})
                            </span>
                        )}
                    </span>
                    {row.must_change_password && (
                        <span className="text-xs font-normal text-warning">
                            {t.mustChange}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'username',
            header: t.username,
            cell: (row) => (
                <span className="font-mono text-sm">{row.username}</span>
            ),
        },
        { key: 'role', header: t.role, cell: (row) => roleLabel(row.role) },
        {
            key: 'status',
            header: t.status,
            cell: (row) => (
                <ActiveToggle
                    checked={row.is_active}
                    disabled={row.is_me}
                    onCheckedChange={() =>
                        router.patch(
                            UserController.toggle.url(row.id),
                            {},
                            {
                                preserveScroll: true,
                                onError: (errors) =>
                                    toast.error(Object.values(errors)[0]),
                            },
                        )
                    }
                />
            ),
        },
        {
            key: 'actions',
            header: t.actions,
            align: 'end',
            cell: (row) => (
                <span className="inline-flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon-lg"
                        onClick={() => open({ kind: 'edit', user: row })}
                        aria-label={`${t.edit} ${row.name}`}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-lg"
                        onClick={() => open({ kind: 'password', user: row })}
                        aria-label={t.resetTitle(row.name)}
                    >
                        <KeyRound />
                    </Button>
                </span>
            ),
        },
    ];

    const addButton = (
        <Button className="h-11" onClick={() => open({ kind: 'new' })}>
            <Plus />
            {t.add}
        </Button>
    );

    return (
        <>
            <Head title={fr.admin.users} />
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {t.description}
                    </p>
                    {addButton}
                </div>
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    empty={<EmptyState icon={Users} title={t.emptyTitle} />}
                />
            </div>
            <UserSheet
                key={openCount}
                mode={mode}
                roles={roles}
                open={sheetOpen}
                onClose={() => setSheetOpen(false)}
            />
        </>
    );
}
