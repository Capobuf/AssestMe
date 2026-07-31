<?php

declare(strict_types=1);

namespace App\Http\Requests\Installation;

use App\Data\Installation\AdministratorData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{name: list<string>, email: list<string>, password: list<string|Password>} */
    public function rules(): array
    {
        $rules = AdministratorData::rules();
        $rules['password'][] = 'confirmed';

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function toData(): AdministratorData
    {
        return AdministratorData::validate(
            (string) $this->validated('name'),
            (string) $this->validated('email'),
            (string) $this->validated('password'),
        );
    }
}
