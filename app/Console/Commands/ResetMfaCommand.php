<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ResetMfaCommand extends Command
{
    protected $signature = 'assestme:reset-mfa {--force : Reset MFA without interactive confirmation}';

    protected $description = 'Disable administrator TOTP MFA and invalidate every recovery code';

    public function handle(): int
    {
        $administrator = User::query()->first();

        if ($administrator === null) {
            $this->components->error(__('assestme.mfa.errors.no_administrator'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(__('assestme.mfa.reset_confirmation'))) {
            $this->components->warn(__('assestme.mfa.reset_cancelled'));

            return self::FAILURE;
        }

        DB::transaction(function () use ($administrator): void {
            $administrator->app_authentication_secret = null;
            $administrator->app_authentication_recovery_codes = null;
            $administrator->mfa_enabled_at = null;
            $administrator->save();
        });

        $this->components->info(__('assestme.mfa.reset_complete'));

        return self::SUCCESS;
    }
}
