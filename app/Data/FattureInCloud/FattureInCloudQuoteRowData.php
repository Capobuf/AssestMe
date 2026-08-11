<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

use App\Actions\FattureInCloud\FattureInCloudQuoteLogic;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class FattureInCloudQuoteRowData
{
    /** @param list<int> $findingIds */
    public function __construct(
        public string $kind,
        public string $title,
        public string $description,
        public float $netPrice,
        public float $quantity,
        public string $measure,
        public float $discount,
        public string $vatTypeId,
        public ?string $productId,
        public ?string $productCode,
        public array $findingIds,
    ) {}

    /** @param array<string, mixed> $state */
    public static function fromState(array $state, int $rowNumber): self
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($state, [
            'kind' => ['required', 'in:group,free'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'net_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'measure' => ['nullable', 'string', 'max:32'],
            'discount' => ['required', 'numeric', 'between:0,100'],
            'vat_type_id' => ['required'],
            'product_id' => ['nullable'],
            'product_code' => ['nullable', 'string', 'max:255'],
            'finding_ids' => ['array'],
            'finding_ids.*' => ['integer', 'min:1'],
        ], [], [
            'title' => __('assestme.fatture_in_cloud.composer.row_number', ['number' => $rowNumber]),
        ])->validate();

        $kind = (string) $validated['kind'];
        /** @var list<int> $findingIds */
        $findingIds = array_values(array_unique(array_map(
            'intval',
            is_array($validated['finding_ids'] ?? null) ? $validated['finding_ids'] : [],
        )));
        if ($kind === 'group' && $findingIds === []) {
            throw ValidationException::withMessages([
                'rows.'.($rowNumber - 1).'.finding_ids' => __('assestme.fatture_in_cloud.composer.group_requires_finding'),
            ]);
        }

        return new self(
            kind: $kind,
            title: trim((string) $validated['title']),
            description: trim((string) ($validated['description'] ?? '')),
            netPrice: (float) $validated['net_price'],
            quantity: (float) $validated['quantity'],
            measure: trim((string) ($validated['measure'] ?? '')),
            discount: (float) $validated['discount'],
            vatTypeId: (string) $validated['vat_type_id'],
            productId: self::optionalIdentifier($validated['product_id'] ?? null),
            productCode: is_string($validated['product_code'] ?? null) ? $validated['product_code'] : null,
            findingIds: $findingIds,
        );
    }

    /** @return array<string, mixed> */
    public function providerItem(): array
    {
        $item = [
            'name' => $this->title,
            'description' => FattureInCloudQuoteLogic::descriptionWithReferences($this->description, $this->findingIds),
            'qty' => $this->quantity,
            'net_price' => $this->netPrice,
            'discount' => $this->discount,
            'vat' => ['id' => self::providerIdentifier($this->vatTypeId)],
        ];
        if ($this->productId !== null) {
            $item['product_id'] = self::providerIdentifier($this->productId);
        }
        if ($this->productCode !== null && $this->productCode !== '') {
            $item['code'] = $this->productCode;
        }
        if ($this->measure !== '') {
            $item['measure'] = $this->measure;
        }

        return $item;
    }

    private static function optionalIdentifier(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public static function providerIdentifier(string $value): int|string
    {
        if (ctype_digit($value) && $value !== '0' && (string) (int) $value === ltrim($value, '0')) {
            return (int) $value;
        }

        return $value;
    }
}
