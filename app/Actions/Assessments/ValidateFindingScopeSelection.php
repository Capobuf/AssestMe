<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\ScopeType;
use Illuminate\Validation\ValidationException;

final class ValidateFindingScopeSelection
{
    /** @return list<string> */
    public function messages(ScopeType $scope, int $siteCount, ?string $description): array
    {
        $messages = [];

        if ($scope === ScopeType::SelectedSites && $siteCount === 0) {
            $messages[] = __('assestme.findings.errors.site_scope_required');
        }
        if (in_array($scope, [ScopeType::Network, ScopeType::Custom], true) && blank($description)) {
            $messages[] = __('assestme.findings.errors.scope_description_required');
        }

        return $messages;
    }

    /** @throws ValidationException */
    public function __invoke(ScopeType $scope, int $siteCount, ?string $description): void
    {
        $messages = $this->messages($scope, $siteCount, $description);

        if ($messages !== []) {
            throw ValidationException::withMessages(['scope' => $messages]);
        }
    }
}
