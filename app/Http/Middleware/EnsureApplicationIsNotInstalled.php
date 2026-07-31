<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Installation\InstallationAnomalyDetector;
use App\Services\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureApplicationIsNotInstalled
{
    public function __construct(
        private InstallationState $installationState,
        private InstallationAnomalyDetector $anomalyDetector,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->installationState->isInstalled(), 404);

        if ($this->anomalyDetector->hasCompletedSchemaWithoutLock()) {
            return new Response(
                'AssestMe rileva uno schema completo e un amministratore senza installation lock. Ripristina il lock da un backup verificato o usa la diagnostica CLI; la reinstallazione web è bloccata.',
                Response::HTTP_CONFLICT,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        return $next($request);
    }
}
