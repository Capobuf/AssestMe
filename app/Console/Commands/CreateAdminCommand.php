<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Installation\CreateSingletonAdministrator;
use App\Data\Installation\AdministratorData;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class CreateAdminCommand extends Command
{
    protected $signature = 'assestme:create-admin
        {--name= : Administrator display name}
        {--email= : Administrator email address}
        {--password= : Administrator password}
        {--from-env : Load administrator values from the DEV_ADMIN_* configuration}
        {--replace : Replace the existing administrator credentials}
        {--current-password= : Current password required for non-interactive replacement}';

    protected $description = 'Create or explicitly replace the single AssestMe administrator';

    public function handle(CreateSingletonAdministrator $createAdministrator): int
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

        $fromEnvironment = (bool) $this->option('from-env');
        $generatedPassword = false;

        if ($fromEnvironment) {
            $name = $this->configuredAdministratorValue('name');
            $email = $this->configuredAdministratorValue('email');
            $password = $this->configuredAdministratorValue('password');

            if ($password === '') {
                $password = bin2hex(random_bytes(12)).'!Aa1';
                $generatedPassword = true;
            }
        } else {
            $name = $this->stringOptionOrAsk('name', __('assestme.admin.prompts.name'));
            $email = $this->stringOptionOrAsk('email', __('assestme.admin.prompts.email'));
            $password = $this->passwordOptionOrAsk();
        }

        try {
            $data = AdministratorData::validate($name, $email, $password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        try {
            if ($existing) {
                $createAdministrator->replace($existing, $data);
            } else {
                $createAdministrator($data);
            }
        } catch (\LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(__('assestme.admin.created'));

        if ($generatedPassword) {
            $this->warn(__('assestme.admin.generated_credentials', [
                'email' => $email,
                'password' => $password,
            ]));
        }

        return self::SUCCESS;
    }

    private function configuredAdministratorValue(string $key): string
    {
        $value = config("assestme.development_administrator.{$key}");

        return is_string($value) ? $value : '';
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
