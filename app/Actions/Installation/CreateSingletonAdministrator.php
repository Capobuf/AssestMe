<?php

declare(strict_types=1);

namespace App\Actions\Installation;

use App\Data\Installation\AdministratorData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateSingletonAdministrator
{
    public function __invoke(AdministratorData $data): User
    {
        return DB::transaction(function () use ($data): User {
            if (User::query()->lockForUpdate()->exists()) {
                throw new LogicException('AssestMe supports exactly one administrator.');
            }

            return User::query()->create($data->toUserAttributes());
        });
    }

    public function replace(User $administrator, AdministratorData $data): User
    {
        return DB::transaction(function () use ($administrator, $data): User {
            $locked = User::query()->lockForUpdate()->find($administrator->getKey());

            if (! $locked instanceof User || User::query()->whereKeyNot($locked->getKey())->exists()) {
                throw new LogicException('AssestMe supports exactly one administrator.');
            }

            $locked->update($data->toUserAttributes());

            return $locked->refresh();
        });
    }
}
