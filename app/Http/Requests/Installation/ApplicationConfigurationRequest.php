<?php

declare(strict_types=1);

namespace App\Http\Requests\Installation;

use App\Data\Installation\ApplicationConfigurationData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ApplicationConfigurationRequest extends FormRequest
{
    private const APPLICATION_NAME = 'AssestMe';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Closure>> */
    public function rules(): array
    {
        $absolutePath = static function (string $attribute, string|int|float|bool|array|null $value, Closure $fail): void {
            if (! is_string($value) || ! str_starts_with($value, DIRECTORY_SEPARATOR)) {
                $fail('Il campo deve contenere un percorso assoluto.');
            }
        };

        return [
            'application_url' => ['required', 'url:http,https', 'max:2048'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'in:it'],
            'backup_root' => ['required', 'string', 'max:4096', $absolutePath],
            'database_driver' => ['required', 'in:sqlite,mysql'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $url = parse_url((string) $this->input('application_url'));

            if (! is_array($url)) {
                return;
            }

            $scheme = strtolower((string) ($url['scheme'] ?? ''));
            $path = (string) ($url['path'] ?? '');

            if (app()->environment('production') && $scheme !== 'https') {
                $validator->errors()->add('application_url', 'In produzione APP_URL deve usare HTTPS.');
            }

            if (! in_array($path, ['', '/'], true) || isset($url['query'], $url['fragment'], $url['user'], $url['pass'])) {
                $validator->errors()->add('application_url', 'APP_URL deve indicare soltanto l’origine, senza percorso, credenziali, query o frammento.');
            }

            $requestedHost = $this->normalizeHost((string) ($url['host'] ?? ''));
            $currentHost = $this->normalizeHost($this->getHost());

            if ($requestedHost === '' || $requestedHost !== $currentHost) {
                $validator->errors()->add('application_url', 'Il dominio di APP_URL deve corrispondere al dominio corrente.');
            }
        }];
    }

    public function toData(string $weasyPrintBinary, string $phpBinary): ApplicationConfigurationData
    {
        /** @var array{application_url: string, timezone: string, locale: string, backup_root: string} $validated */
        $validated = $this->validated();

        return new ApplicationConfigurationData(
            name: self::APPLICATION_NAME,
            url: rtrim($validated['application_url'], '/'),
            timezone: $validated['timezone'],
            locale: $validated['locale'],
            backupRoot: $validated['backup_root'],
            weasyPrintBinary: $weasyPrintBinary,
            phpBinary: $phpBinary,
        );
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
