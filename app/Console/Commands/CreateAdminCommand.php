<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CreateAdminCommand extends Command
{
    protected $signature = 'assestme:create-admin
        {--name= : Administrator display name}
        {--email= : Administrator email address}
        {--password= : Administrator password}
        {--replace : Replace the existing administrator credentials}
        {--current-password= : Current password required for non-interactive replacement}';

    protected $description = 'Create or explicitly replace the single AssestMe administrator';

    public function handle(): int
    {
        $existing = User::query()->first();
        $replace = (bool) $this->option('replace');

        if ($existing && ! $replace) {
            $this->error(__('assestme.admin.errors.already_exists'));

            return self::FAILURE;
        }

        if ($existing && ! $this->replacementIsAuthorized($existing)) {
            return self::FAILURE;
        }

        $name = $this->stringOptionOrAsk('name', __('assestme.admin.prompts.name'));
        $email = $this->stringOptionOrAsk('email', __('assestme.admin.prompts.email'));
        $password = $this->passwordOptionOrAsk();

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => [
                'required',
                'string',
                Password::min(14)->max(128)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            User::query()->create($attributes);
        }

        $this->info(__('assestme.admin.created'));

        return self::SUCCESS;
    }

    private function replacementIsAuthorized(User $existing): bool
    {
        $currentPassword = $this->option('current-password');

        if (is_string($currentPassword) && $currentPassword !== '') {
            if (Hash::check($currentPassword, $existing->password)) {
                return true;
            }

            $this->error(__('assestme.admin.errors.current_password'));

            return false;
        }

        if (! $this->input->isInteractive()) {
            $this->error(__('assestme.admin.errors.replace_confirmation'));

            return false;
        }

        return $this->confirm(__('assestme.admin.prompts.replace_confirmation'));
    }

    private function stringOptionOrAsk(string $option, string $question): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return (string) $this->ask($question);
    }

    private function passwordOptionOrAsk(): string
    {
        $password = $this->option('password');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        return (string) $this->secret(__('assestme.admin.prompts.password'));
    }
}
