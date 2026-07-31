<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureApplicationIsInstalled
{
    public function __construct(private InstallationState $installationState) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installationState->isInstalled() || $request->is(
            'install',
            'install/*',
            'up',
            'css/assestme-installer.css',
            'js/assestme-installer.js',
        )) {
            return $next($request);
        }

        return redirect('/install');
    }
}
