<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Models\Finding;
use App\Models\FindingTemplate;
use Normalizer;

final class FindRelatedFindingTemplates
{
    public const SAME_CATEGORY_THRESHOLD = 0.86;

    public const CROSS_CATEGORY_TITLE_THRESHOLD = 0.96;

    public const MAX_SIMILAR_CANDIDATES = 3;

    public function __construct(private readonly FindingTemplateContent $content) {}

    /**
     * @return array{exact:?FindingTemplate,similar:list<array{template:FindingTemplate,score:float}>}
     */
    public function forFinding(Finding $finding): array
    {
        $finding->loadMissing('solutions');
        $signature = $this->content->findingSemanticSignature($finding);
        $templates = FindingTemplate::query()
            ->with(['category', 'solutions'])
            ->orderByDesc('is_enabled')
            ->orderBy('title')
            ->orderBy('external_id')
            ->get();

        $exact = $templates->first(
            fn (FindingTemplate $template): bool => hash_equals(
                $signature,
                $this->content->templateSemanticSignature($template),
            ),
        );
        if ($exact instanceof FindingTemplate) {
            return ['exact' => $exact, 'similar' => []];
        }

        $findingTitle = $this->normalize((string) $finding->title);
        $findingTokens = $this->tokens((string) $finding->title.' '.(string) $finding->problem);
        $candidates = [];

        foreach ($templates as $template) {
            $templateTitle = $this->normalize($template->title);
            similar_text($findingTitle, $templateTitle, $titlePercent);
            $titleScore = $titlePercent / 100;

            if ((int) $template->category_id === (int) $finding->category_id) {
                $problemTokens = $this->tokens($template->title.' '.$template->problem);
                $union = array_unique([...$findingTokens, ...$problemTokens]);
                $jaccard = $union === [] ? 0.0 : count(array_intersect($findingTokens, $problemTokens)) / count($union);
                $score = (0.7 * $titleScore) + (0.3 * $jaccard);
                if ($score < self::SAME_CATEGORY_THRESHOLD) {
                    continue;
                }
            } else {
                if ($titleScore < self::CROSS_CATEGORY_TITLE_THRESHOLD) {
                    continue;
                }
                $score = $titleScore;
            }

            $candidates[] = ['template' => $template, 'score' => round($score, 6)];
        }

        usort($candidates, static fn (array $left, array $right): int => [
            -$left['score'],
            $left['template']->title,
            $left['template']->external_id,
        ] <=> [
            -$right['score'],
            $right['template']->title,
            $right['template']->external_id,
        ]);

        return [
            'exact' => null,
            'similar' => array_slice($candidates, 0, self::MAX_SIMILAR_CANDIDATES),
        ];
    }

    private function normalize(string $value): string
    {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        $normalized = mb_strtolower($normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/\s+/u', $this->normalize($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 3,
        )));
        sort($tokens);

        return $tokens;
    }
}
