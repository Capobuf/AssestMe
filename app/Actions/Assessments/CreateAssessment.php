<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateAssessment
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Assessment
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'report_title_override' => ['nullable', 'string', 'max:255'],
            'assessment_date' => ['required', 'date_format:Y-m-d'],
            'scope_type' => ['required', Rule::enum(ScopeType::class)],
            'scope_description' => ['nullable', 'string', 'max:20000'],
            'site_ids' => ['array'],
            'site_ids.*' => ['integer', 'distinct', Rule::exists('sites', 'id')->whereNull('deleted_at')],
            'introduction' => ['nullable', 'string', 'max:20000'],
            'executive_summary' => ['nullable', 'string', 'max:20000'],
            'methodology_notes' => ['nullable', 'string', 'max:20000'],
        ])->validate();

        $client = Client::query()->findOrFail($validated['client_id']);
        $siteIds = array_map('intval', $validated['site_ids'] ?? []);

        if ($client->sites()->whereIn('id', $siteIds)->count() !== count($siteIds)) {
            throw ValidationException::withMessages([
                'site_ids' => __('assestme.assessments.errors.site_ownership'),
            ]);
        }

        return DB::transaction(function () use ($validated, $siteIds): Assessment {
            $assessment = Assessment::query()->create([
                ...collect($validated)->except('site_ids')->all(),
                'locale' => 'it',
                'status' => AssessmentStatus::Draft,
                'lock_version' => 0,
            ]);
            $assessment->sites()->sync($siteIds);

            return $assessment->refresh();
        });
    }
}
