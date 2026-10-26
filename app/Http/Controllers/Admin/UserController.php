<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comptes de l'application (gérant seulement). Le dernier gérant actif ne peut être ni désactivé ni rétrogradé.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/users', [
            'rows' => User::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(50)
                ->through(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role->value,
                    'is_active' => $user->is_active,
                    'must_change_password' => $user->must_change_password,
                    'is_me' => $user->is($request->user()),
                ]),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(null),
            'password' => ['required', 'string', Password::defaults()],
        ], [], self::ATTRIBUTES);

        $user = User::create([...$data, 'must_change_password' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Compte « {$user->username} » créé : le mot de passe sera changé à la première connexion."]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user), [], self::ATTRIBUTES);
        $role = UserRole::from($data['role']);

        if ($role !== $user->role) {
            if ($user->is($request->user())) {
                throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas changer votre propre rôle.']);
            }

            $this->keepOneManager($user);
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compte modifié.']);

        return back();
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is_active) {
            if ($user->is($request->user())) {
                throw ValidationException::withMessages(['is_active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
            }

            $this->keepOneManager($user);
        }

        $user->update(['is_active' => ! $user->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $user->is_active ? 'Compte réactivé.' : 'Compte désactivé : il est déconnecté.']);

        return back();
    }

    /**
     * Mot de passe provisoire donné par le gérant : l'utilisateur en choisit un nouveau à sa connexion suivante.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', Password::defaults()]], [], self::ATTRIBUTES);

        $user->update([
            'password' => $data['password'],
            'must_change_password' => ! $user->is($request->user()),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Mot de passe de « {$user->username} » réinitialisé."]);

        return back();
    }

    /**
     * Refuse de retirer le dernier gérant actif (sinon plus personne ne gère les comptes ni les sauvegardes).
     */
    private function keepOneManager(User $user): void
    {
        $others = User::where('role', UserRole::Gerant)->where('is_active', true)->whereKeyNot($user->id)->exists();

        if ($user->isGerant() && $user->is_active && ! $others) {
            throw ValidationException::withMessages(['role' => 'C’est le dernier gérant actif : nommez d’abord un autre gérant.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    private const ATTRIBUTES = [
        'name' => 'nom',
        'username' => 'nom d’utilisateur',
        'role' => 'rôle',
        'password' => 'mot de passe',
    ];
}
