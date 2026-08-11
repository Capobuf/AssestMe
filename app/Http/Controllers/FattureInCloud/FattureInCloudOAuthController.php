<?php

declare(strict_types=1);

namespace App\Http\Controllers\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudVatTypeData;
use App\Filament\Pages\FattureInCloudSettingsPage;
use App\Http\Controllers\Controller;
use App\Services\FattureInCloud\FattureInCloudApi;
use App\Services\FattureInCloud\FattureInCloudConfiguration;
use App\Services\FattureInCloud\FattureInCloudException;
use App\Services\FattureInCloud\FattureInCloudTokenService;
use App\Settings\FattureInCloudSettings;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class FattureInCloudOAuthController extends Controller
{
    private const SESSION_STATE = 'fatture_in_cloud.oauth_state';

    public function redirect(
        Request $request,
        FattureInCloudConfiguration $configuration,
    ): RedirectResponse {
        if (! $configuration->isComplete()) {
            Notification::make()->danger()
                ->title(__('assestme.fatture_in_cloud.errors.configuration_incomplete'))
                ->persistent()
                ->send();

            return $this->settingsRedirect();
        }

        $state = Str::random(64);
        $request->session()->put(self::SESSION_STATE, $state);

        return redirect()->away(FattureInCloudConfiguration::BASE_URL.'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $configuration->clientId(),
            'redirect_uri' => $configuration->callbackUri(),
            'scope' => FattureInCloudConfiguration::SCOPES,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(
        Request $request,
        FattureInCloudTokenService $tokens,
        FattureInCloudApi $api,
        FattureInCloudSettings $settings,
    ): RedirectResponse {
        $expectedState = $request->session()->pull(self::SESSION_STATE);
        $state = $request->query('state');
        $code = $request->query('code');
        if ((! is_string($expectedState) || $expectedState === '')
            && $this->connectionEstablished($settings)
            && is_string($state) && $state !== ''
            && is_string($code) && $code !== '') {
            Log::notice('Fatture in Cloud OAuth callback replay was ignored after connection.');

            return $this->settingsRedirect();
        }
        $invalidReason = $this->invalidCallbackReason($request, $expectedState, $state, $code);
        if ($invalidReason !== null) {
            Log::warning('Fatture in Cloud OAuth callback rejected.', [
                'reason' => $invalidReason,
            ]);
            Notification::make()->danger()
                ->title($invalidReason === 'provider_rejected'
                    ? __('assestme.fatture_in_cloud.errors.oauth_rejected')
                    : __('assestme.fatture_in_cloud.errors.oauth_state'))
                ->persistent()
                ->send();

            return $this->settingsRedirect();
        }

        $stage = 'token_exchange';
        try {
            $tokens->exchangeAuthorizationCode($code);
            $stage = 'company_discovery';
            $companies = $api->companies();
            if (count($companies) !== 1) {
                $tokens->clearConnection();
                Log::warning('Fatture in Cloud OAuth company discovery rejected.', [
                    'stage' => $stage,
                    'company_count' => count($companies),
                ]);
                Notification::make()->danger()
                    ->title(count($companies) === 0
                        ? __('assestme.fatture_in_cloud.errors.no_company')
                        : __('assestme.fatture_in_cloud.errors.multiple_companies'))
                    ->persistent()
                    ->send();

                return $this->settingsRedirect();
            }

            $company = $companies[0];
            $settings->company_id = $company->id;
            $settings->company_name = $company->name;
            $stage = 'vat_discovery';
            $vatTypes = array_values(array_filter(
                $api->vatTypes($company->id),
                static fn (FattureInCloudVatTypeData $vatType): bool => ! $vatType->disabled,
            ));
            $providerDefaults = array_values(array_filter(
                $vatTypes,
                static fn (FattureInCloudVatTypeData $vatType): bool => $vatType->default,
            ));
            $default = count($providerDefaults) === 1 ? $providerDefaults[0] : null;
            $settings->default_vat_type_id = $default?->id;
            $settings->default_vat_type_label = $default?->label();
            $settings->scope_version = FattureInCloudConfiguration::SCOPE_VERSION;
            $stage = 'settings_persist';
            $settings->save();

            Notification::make()
                ->success()
                ->title($default === null
                    ? __('assestme.fatture_in_cloud.notifications.connected_choose_vat')
                    : __('assestme.fatture_in_cloud.notifications.connected'))
                ->send();
        } catch (Throwable $exception) {
            Log::warning('Fatture in Cloud OAuth callback failed.', [
                'stage' => $stage,
                'exception' => $exception::class,
                'reason' => $exception instanceof FattureInCloudException
                    ? $exception->reason
                    : 'unexpected_exception',
            ]);
            try {
                $tokens->clearConnection();
            } catch (Throwable $cleanupException) {
                Log::error('Fatture in Cloud OAuth connection cleanup failed.', [
                    'exception' => $cleanupException::class,
                ]);
            }
            Notification::make()->danger()
                ->title(__('assestme.fatture_in_cloud.errors.oauth_failed'))
                ->body($exception instanceof FattureInCloudException
                    ? $exception->getMessage()
                    : __('assestme.fatture_in_cloud.errors.provider_unavailable'))
                ->persistent()
                ->send();
        }

        return $this->settingsRedirect();
    }

    private function settingsRedirect(): RedirectResponse
    {
        return redirect(FattureInCloudSettingsPage::getUrl(isAbsolute: false));
    }

    private function invalidCallbackReason(
        Request $request,
        mixed $expectedState,
        mixed $state,
        mixed $code,
    ): ?string {
        if (! is_string($expectedState) || $expectedState === '') {
            return 'missing_session_state';
        }

        if (! is_string($state) || $state === '') {
            return 'missing_callback_state';
        }

        if (! hash_equals($expectedState, $state)) {
            return 'state_mismatch';
        }

        if ($request->filled('error')) {
            return 'provider_rejected';
        }

        return ! is_string($code) || $code === '' ? 'missing_code' : null;
    }

    private function connectionEstablished(FattureInCloudSettings $settings): bool
    {
        return is_string($settings->encrypted_access_token) && $settings->encrypted_access_token !== ''
            && is_string($settings->encrypted_refresh_token) && $settings->encrypted_refresh_token !== ''
            && is_string($settings->company_id) && $settings->company_id !== '';
    }
}
