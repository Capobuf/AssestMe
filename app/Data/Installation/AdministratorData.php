<?php

declare(strict_types=1);

namespace App\Data\Installation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

final readonly class AdministratorData
{
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter]
        public string $password,
    ) {}

    /**
     * @return array{name: list<string>, email: list<string>, password: list<string|Password>}
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => [
                'required',
                'string',
                Password::min(14)->max(128)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public static function validate(
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
    ): self {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = Validator::make([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
        ], self::rules())->validate();

        return new self(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
        );
    }

    /** @return array{name: string, email: string, password: string} */
    public function toUserAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
