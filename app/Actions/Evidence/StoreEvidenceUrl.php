<?php

declare(strict_types=1);

namespace App\Actions\Evidence;

use App\Enums\AssessmentStatus;
use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\Finding;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StoreEvidenceUrl
{
    /** @param array<string, mixed> $data */
    public function handle(Finding $finding, array $data): Evidence
    {
        $finding->loadMissing('assessment');
        if ($finding->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['evidence' => __('assestme.assessments.errors.read_only')]);
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:20000'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
            'include_in_report' => ['required', 'boolean'],
        ])->validate();

        $parts = parse_url((string) $validated['url']);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['url' => __('assestme.evidence.errors.url_credentials')]);
        }

        return $finding->evidences()->create([
            ...$validated,
            'type' => EvidenceType::Url,
            'sort_order' => ((int) $finding->evidences()->max('sort_order')) + 1,
        ]);
    }
}
