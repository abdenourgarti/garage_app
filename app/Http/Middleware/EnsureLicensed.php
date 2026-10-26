<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sans licence valide pour ce PC, seul l'écran d'activation répond. Coupé par LICENSE_ENABLED=false.
 * Le résultat est gardé une heure dans la session.
 */
class EnsureLicensed
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->license->enabled() || $request->routeIs('license.*')) {
            return $next($request);
        }

        $until = (int) $request->session()->get('license.checked_until', 0);

        if ($until > time()) {
            return $next($request);
        }

        if (! $this->license->isActivated()) {
            return to_route('license.show');
        }

        $request->session()->put('license.checked_until', time() + 3600);

        return $next($request);
    }
}
