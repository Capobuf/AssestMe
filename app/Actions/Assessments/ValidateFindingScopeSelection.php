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
        $errors = [];
        if ($scope === ScopeType::SelectedSites && $siteCount === 0) {
            $errors['site_ids'][] = __('assestme.findings.errors.site_scope_required');
        }
        if (in_array($scope, [ScopeType::Network, ScopeType::Custom], true) && blank($description)) {
            $errors['scope_description'][] = __('assestme.findings.errors.scope_description_required');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
