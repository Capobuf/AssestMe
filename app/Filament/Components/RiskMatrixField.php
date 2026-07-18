<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Closure;
use Filament\Forms\Components\Concerns\HasNestedRecursiveValidationRules;
use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;

final class RiskMatrixField extends Field implements HasNestedRecursiveValidationRulesContract
{
    use HasNestedRecursiveValidationRules;

    /** @var view-string */
    protected string $view = 'filament.components.risk-matrix-field';

    /** @var array<int|string, mixed>|Closure */
    private array|Closure $consequences = [];

    /** @var array<int|string, mixed>|Closure */
    private array|Closure $likelihoods = [];

    /** @var array<int|string, mixed>|Closure */
    private array|Closure $priorities = [];

    /** @param array<int|string, mixed>|Closure $consequences */
    public function consequences(array|Closure $consequences): static
    {
        $this->consequences = $consequences;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $likelihoods */
    public function likelihoods(array|Closure $likelihoods): static
    {
        $this->likelihoods = $likelihoods;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $priorities */
    public function priorities(array|Closure $priorities): static
    {
        $this->priorities = $priorities;

        return $this;
    }

    /**
     * @return list<array{identity: string, label: string, color: string, is_enabled: bool, sort_order: int}>
     */
    public function getConsequences(): array
    {
        return $this->normalizeLevels($this->evaluate($this->consequences));
    }

    /**
     * @return list<array{identity: string, label: string, color: string, is_enabled: bool, sort_order: int}>
     */
    public function getLikelihoods(): array
    {
        return $this->normalizeLevels($this->evaluate($this->likelihoods));
    }

    /**
     * @return list<array{identity: string, label: string, color: string, is_enabled: bool, sort_order: int}>
     */
    public function getPriorities(): array
    {
        return $this->normalizeLevels($this->evaluate($this->priorities));
    }

    /**
     * @return array<string, array{identity: string, label: string, color: string, is_enabled: bool, sort_order: int}>
     */
    public function getPrioritiesByIdentity(): array
    {
        $indexed = [];

        foreach ($this->getPriorities() as $priority) {
            $indexed[$priority['identity']] = $priority;
        }

        return $indexed;
    }

    /**
     * @param  array<int|string, mixed>  $levels
     * @return list<array{identity: string, label: string, color: string, is_enabled: bool, sort_order: int}>
     */
    private function normalizeLevels(array $levels): array
    {
        $normalized = [];

        foreach ($levels as $formKey => $level) {
            if (! is_array($level)) {
                continue;
            }

            $identity = filled($level['id'] ?? null)
                ? (string) $level['id']
                : (string) ($level['_form_key'] ?? $formKey);

            if ($identity === '') {
                continue;
            }

            $color = mb_strtoupper((string) ($level['color'] ?? ''));
            if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
                $color = '#6B7280';
            }

            $normalized[] = [
                'identity' => $identity,
                'label' => trim((string) ($level['label'] ?? '')),
                'color' => $color,
                'is_enabled' => (bool) ($level['is_enabled'] ?? false),
                'sort_order' => (int) ($level['sort_order'] ?? 0),
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => [
            $left['sort_order'],
            $left['identity'],
        ] <=> [
            $right['sort_order'],
            $right['identity'],
        ]);

        return $normalized;
    }
}
