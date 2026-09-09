<?php

declare(strict_types=1);

use Opis\JsonSchema\Validator;

it('validates a schema v2 template with custom risk codes using Opis', function (): void {
    $schema = json_decode((string) file_get_contents(base_path('schemas/finding-template.schema.json')));
    $document = json_decode((string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')));
    $document->schema_version = 2;
    foreach ($document->templates as $template) {
        foreach ($template->solutions as $solution) {
            if ($solution->estimate_type === 'exact') {
                $solution->estimate_type = 'approximate';
            }
        }
    }
    $document->templates[0]->consequence = 'custom_consequence';
    $document->templates[0]->likelihood = 'custom_likelihood';
    $document->templates[0]->priority = 'custom_priority';
    $result = (new Validator)->validate($document, $schema);

    expect($result->isValid())->toBeTrue();
});

it('rejects a structurally invalid template fixture with Opis', function (): void {
    $schema = json_decode((string) file_get_contents(base_path('schemas/finding-template-v1.schema.json')));
    $document = json_decode((string) file_get_contents(base_path('fixtures/imports/invalid-custom-billing.json')));
    $result = (new Validator)->validate($document, $schema);

    expect($result->isValid())->toBeFalse();
});

it('rejects a fourth solution through the canonical JSON schema', function (): void {
    $schema = json_decode((string) file_get_contents(base_path('schemas/finding-template.schema.json')));
    $document = json_decode((string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')));
    $document->schema_version = 2;
    $extra = clone $document->templates[2]->solutions[1];
    $extra->external_id = 'fourth-solution';
    $extra->title = 'Quarta soluzione';
    $document->templates[2]->solutions[] = $extra;
    $result = (new Validator)->validate($document, $schema);

    expect($result->isValid())->toBeFalse();
});
