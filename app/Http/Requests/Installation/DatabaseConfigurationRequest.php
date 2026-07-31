<?php

declare(strict_types=1);

namespace App\Http\Requests\Installation;

use App\Data\Installation\DatabaseConfigurationData;
use App\Enums\SupportedDatabaseDriver;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class DatabaseConfigurationRequest extends FormRequest
{
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
            'database_driver' => ['required', 'in:sqlite,mysql,mariadb'],
            'sqlite_path' => ['required_if:database_driver,sqlite', 'nullable', 'string', 'max:4096', $absolutePath],
            'database_host' => ['required_unless:database_driver,sqlite', 'nullable', 'string', 'max:253'],
            'database_port' => ['required_unless:database_driver,sqlite', 'nullable', 'integer', 'between:1,65535'],
            'database_name' => ['required_unless:database_driver,sqlite', 'nullable', 'string', 'max:128'],
            'database_username' => ['required_unless:database_driver,sqlite', 'nullable', 'string', 'max:128'],
            'database_password' => ['nullable', 'string', 'max:4096'],
            'database_socket' => ['nullable', 'string', 'max:4096'],
            'database_charset' => ['required_unless:database_driver,sqlite', 'nullable', 'in:utf8mb4'],
            'database_collation' => ['required_unless:database_driver,sqlite', 'nullable', 'in:utf8mb4_unicode_ci'],
            'dump_binary' => ['required_unless:database_driver,sqlite', 'nullable', 'string', 'max:4096', $absolutePath],
            'restore_binary' => ['required_unless:database_driver,sqlite', 'nullable', 'string', 'max:4096', $absolutePath],
        ];
    }

    public function toData(): DatabaseConfigurationData
    {
        $driver = SupportedDatabaseDriver::from((string) $this->validated('database_driver'));

        return new DatabaseConfigurationData(
            driver: $driver,
            database: $driver === SupportedDatabaseDriver::Sqlite
                ? (string) $this->validated('sqlite_path')
                : (string) $this->validated('database_name'),
            host: (string) ($this->validated('database_host') ?? '127.0.0.1'),
            port: (int) ($this->validated('database_port') ?? 3306),
            username: (string) ($this->validated('database_username') ?? ''),
            password: (string) ($this->validated('database_password') ?? ''),
            socket: (string) ($this->validated('database_socket') ?? ''),
            charset: (string) ($this->validated('database_charset') ?? 'utf8mb4'),
            collation: (string) ($this->validated('database_collation') ?? 'utf8mb4_unicode_ci'),
            dumpBinary: (string) ($this->validated('dump_binary') ?? ''),
            restoreBinary: (string) ($this->validated('restore_binary') ?? ''),
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->request->remove('database_password');

        throw new HttpResponseException(
            redirect()->back()->withErrors($validator)->withInput($this->except('database_password')),
        );
    }
}
