<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte désactivé par le gérant : déconnecté dès la page suivante.
 * Mot de passe provisoire : seule la page « Mot de passe » reste accessible tant qu'il n'est pas changé.
 */
class EnsureAccountIsUsable
{
    private const ALLOWED_WHILE_TEMPORARY = ['security.edit', 'user-password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors(['username' => 'Ce compte est désactivé. Contactez le gérant.']);
        }

        if ($user?->must_change_password && ! $request->routeIs(self::ALLOWED_WHILE_TEMPORARY)) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Choisissez votre mot de passe personnel avant de continuer.']);

            return to_route('security.edit');
        }

        return $next($request);
    }
}
