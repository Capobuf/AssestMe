<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Clusters\AssetCluster;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Resources\Assets\AssetResource;
use Baspa\FilamentCanary\Sweep\Requester;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Canary 1.2 classifies every authorized redirect as an authentication gap.
 * Native Filament clusters intentionally redirect to their first real page, so
 * this adapter follows only those two exact, verified redirect destinations.
 */
final readonly class CanaryClusterRequester implements Requester
{
    public function __construct(
        private HttpKernel $kernel,
        private AuthFactory $auth,
    ) {}

    public function get(string $url, ?Authenticatable $user, string $guard): int
    {
        $this->setUser($user, $guard);

        try {
            $request = Request::create($url, 'GET');
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);

            if ($user === null || ! $response instanceof RedirectResponse) {
                return $response->getStatusCode();
            }

            $expectedDestination = $this->expectedClusterDestination($request->route()?->getName());
            if ($expectedDestination === null
                || $this->pathAndQuery($response->getTargetUrl()) !== $this->pathAndQuery($expectedDestination)) {
                return $response->getStatusCode();
            }

            $destinationRequest = Request::create($response->getTargetUrl(), 'GET');
            $destinationResponse = $this->kernel->handle($destinationRequest);
            $this->kernel->terminate($destinationRequest, $destinationResponse);

            return $destinationResponse->getStatusCode();
        } catch (\Throwable) {
            return 500;
        } finally {
            $this->forgetUser($guard);
        }
    }

    private function expectedClusterDestination(?string $routeName): ?string
    {
        return match ($routeName) {
            AssetCluster::getRouteName() => AssetResource::getUrl('index'),
            SettingsCluster::getRouteName() => GeneralSettingsPage::getUrl(),
            default => null,
        };
    }

    private function pathAndQuery(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return (is_string($path) ? $path : '/').(is_string($query) ? "?{$query}" : '');
    }

    private function setUser(?Authenticatable $user, string $guard): void
    {
        if ($user === null) {
            $this->forgetUser($guard);

            return;
        }

        $this->auth->guard($guard)->setUser($user);
    }

    private function forgetUser(string $guard): void
    {
        $guardInstance = $this->auth->guard($guard);

        if (method_exists($guardInstance, 'forgetUser')) {
            $guardInstance->forgetUser();
        }
    }
}
