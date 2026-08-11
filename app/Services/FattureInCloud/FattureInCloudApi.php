<?php

declare(strict_types=1);

namespace App\Services\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudClientData;
use App\Data\FattureInCloud\FattureInCloudCompanyData;
use App\Data\FattureInCloud\FattureInCloudDocumentData;
use App\Data\FattureInCloud\FattureInCloudDocumentItemData;
use App\Data\FattureInCloud\FattureInCloudProductData;
use App\Data\FattureInCloud\FattureInCloudVatTypeData;
use App\Models\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class FattureInCloudApi
{
    public function __construct(private FattureInCloudTokenService $tokens) {}

    /** @return list<FattureInCloudCompanyData> */
    public function companies(): array
    {
        $response = $this->get('/user/companies');
        if (! $response instanceof Response) {
            throw FattureInCloudException::invalidResponse();
        }
        $body = $this->body($response);
        $data = $body['data'] ?? null;
        $companies = is_array($data) ? ($data['companies'] ?? null) : null;
        if (! is_array($companies)) {
            throw FattureInCloudException::invalidResponse();
        }

        $result = [];
        foreach ($companies as $company) {
            if (! is_array($company)) {
                throw FattureInCloudException::invalidResponse();
            }

            $id = $this->identifier($company['id'] ?? null);
            $name = $company['name'] ?? null;
            if ($id === null || ! is_string($name) || trim($name) === '') {
                throw FattureInCloudException::invalidResponse();
            }
            $result[] = new FattureInCloudCompanyData($id, trim($name));
        }

        return $result;
    }

    /** @return list<FattureInCloudVatTypeData> */
    public function vatTypes(string $companyId): array
    {
        $response = $this->get('/c/'.rawurlencode($companyId).'/info/vat_types', [
            'fieldset' => 'detailed',
        ]);
        if (! $response instanceof Response) {
            throw FattureInCloudException::invalidResponse();
        }
        $body = $this->body($response);
        $vatTypes = $body['data'] ?? null;
        if ($vatTypes === null) {
            return [];
        }
        if (! is_array($vatTypes)) {
            $this->logInvalidVatResponse('data_not_array');

            throw FattureInCloudException::invalidResponse();
        }

        $result = [];
        foreach ($vatTypes as $index => $vatType) {
            if ($vatType === null) {
                $this->logUnaddressableVatType('null_item', $index);

                continue;
            }
            if (! is_array($vatType)) {
                $this->logInvalidVatResponse('item_not_object', $index);

                throw FattureInCloudException::invalidResponse();
            }
            $id = $this->identifier($vatType['id'] ?? null);
            $value = $vatType['value'] ?? null;
            $description = $vatType['description'] ?? '';
            if ($id === null) {
                $this->logUnaddressableVatType('missing_id', $index);

                continue;
            }
            if ($value !== null && ! is_numeric($value)) {
                $this->logInvalidVatResponse('invalid_value_type', $index);

                throw FattureInCloudException::invalidResponse();
            }
            if (! is_string($description)) {
                $this->logInvalidVatResponse('invalid_description_type', $index);

                throw FattureInCloudException::invalidResponse();
            }
            $result[] = new FattureInCloudVatTypeData(
                id: $id,
                value: $value === null ? null : (float) $value,
                description: trim($description),
                disabled: (bool) ($vatType['is_disabled'] ?? false),
                default: (bool) ($vatType['default'] ?? false),
            );
        }

        return $result;
    }

    public function client(string $companyId, string $clientId): ?FattureInCloudClientData
    {
        $response = $this->get(
            '/c/'.rawurlencode($companyId).'/entities/clients/'.rawurlencode($clientId),
            ['fieldset' => 'detailed'],
            allowNotFound: true,
        );

        if (! $response instanceof Response) {
            return null;
        }

        $body = $this->body($response);
        $data = $body['data'] ?? null;

        return $this->parseClient($data);
    }

    /** @return list<FattureInCloudClientData> */
    public function clients(string $companyId, string $field, string $identifier): array
    {
        $filter = FattureInCloudQuery::fiscalIdentifier($field, $identifier);
        $page = 1;
        $lastPage = 1;
        $clients = [];
        do {
            $response = $this->get('/c/'.rawurlencode($companyId).'/entities/clients', [
                'q' => $filter,
                'fieldset' => 'detailed',
                'page' => $page,
                'per_page' => 100,
            ]);
            if (! $response instanceof Response) {
                throw FattureInCloudException::invalidResponse();
            }
            $body = $this->body($response);
            $data = $body['data'] ?? null;
            if (! is_array($data)) {
                throw FattureInCloudException::invalidResponse();
            }
            foreach ($data as $item) {
                $client = $this->parseClient($item);
                $clients[$client->id] = $client;
            }
            $lastPage = $this->lastPage($body, $page);
            $page++;
        } while ($page <= $lastPage);

        return array_values($clients);
    }

    public function createClient(string $companyId, Client $client): FattureInCloudClientData
    {
        $data = array_filter([
            'type' => 'company',
            'name' => $client->legal_name,
            'vat_number' => $client->vat_number,
            'tax_code' => $client->tax_code,
            'email' => $client->email,
            'phone' => $client->phone,
            'address_street' => $client->address,
            'address_postal_code' => $client->postal_code,
            'address_city' => $client->city,
            'address_province' => $client->province,
            'country' => $client->country === 'IT' ? 'Italia' : $client->country,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->post('/c/'.rawurlencode($companyId).'/entities/clients', ['data' => $data]);
        $body = $this->body($response);

        return $this->parseClient($body['data'] ?? null);
    }

    /** @return list<FattureInCloudProductData> */
    public function products(string $companyId, string $query = ''): array
    {
        $page = 1;
        $lastPage = 1;
        $products = [];
        do {
            $parameters = [
                'fieldset' => 'detailed',
                'page' => $page,
                'per_page' => 100,
            ];
            if ($query !== '') {
                $parameters['q'] = FattureInCloudQuery::productSearch($query);
            }
            $response = $this->get('/c/'.rawurlencode($companyId).'/products', $parameters);
            if (! $response instanceof Response) {
                throw FattureInCloudException::invalidResponse();
            }
            $body = $this->body($response);
            if (! is_array($body['data'] ?? null)) {
                throw FattureInCloudException::invalidResponse();
            }
            foreach ($body['data'] as $item) {
                $product = $this->parseProduct($item);
                $products[$product->id] = $product;
            }
            $lastPage = $this->lastPage($body, $page++);
        } while ($page <= $lastPage);

        return array_values($products);
    }

    public function product(string $companyId, string $productId): ?FattureInCloudProductData
    {
        $response = $this->get(
            '/c/'.rawurlencode($companyId).'/products/'.rawurlencode($productId),
            ['fieldset' => 'detailed'],
            allowNotFound: true,
        );
        if (! $response instanceof Response) {
            return null;
        }

        return $this->parseProduct($this->body($response)['data'] ?? null);
    }

    /** @return list<FattureInCloudDocumentData> */
    public function quotes(string $companyId, string $query): array
    {
        $filter = FattureInCloudQuery::documentSubject($query);
        $page = 1;
        $lastPage = 1;
        $documents = [];
        do {
            $response = $this->get('/c/'.rawurlencode($companyId).'/issued_documents', [
                'type' => 'quote', 'q' => $filter, 'fieldset' => 'detailed',
                'page' => $page, 'per_page' => 100,
            ]);
            if (! $response instanceof Response) {
                throw FattureInCloudException::invalidResponse();
            }
            $body = $this->body($response);
            if (! is_array($body['data'] ?? null)) {
                throw FattureInCloudException::invalidResponse();
            }
            foreach ($body['data'] as $item) {
                $document = $this->parseDocument($item);
                $documents[$document->id] = $document;
            }
            $lastPage = $this->lastPage($body, $page++);
        } while ($page <= $lastPage);

        return array_values($documents);
    }

    public function quote(string $companyId, string $documentId): ?FattureInCloudDocumentData
    {
        $response = $this->get(
            '/c/'.rawurlencode($companyId).'/issued_documents/'.rawurlencode($documentId),
            ['fieldset' => 'detailed'], allowNotFound: true,
        );

        return $response instanceof Response
            ? $this->parseDocument($this->body($response)['data'] ?? null)
            : null;
    }

    /** @param array<string, mixed> $data */
    public function createQuote(string $companyId, array $data): FattureInCloudDocumentData
    {
        $response = $this->post(
            '/c/'.rawurlencode($companyId).'/issued_documents',
            ['data' => $data],
            ambiguousOnConnectionFailure: true,
        );

        return $this->parseDocument($this->body($response)['data'] ?? null);
    }

    /** @param array<string, int|string> $query */
    private function get(string $path, array $query = [], bool $allowNotFound = false): ?Response
    {
        $response = $this->sendGet($path, $query, $this->tokens->accessToken());
        if ($response->status() === 401) {
            $response = $this->sendGet($path, $query, $this->tokens->refreshAccessToken());
        }

        if ($response->successful()) {
            return $response;
        }

        Log::warning('Fatture in Cloud API request was rejected.', [
            'operation' => $this->operation($path),
            'status' => $response->status(),
        ]);

        if ($allowNotFound && $response->status() === 404) {
            return null;
        }
        if ($response->status() === 401) {
            throw FattureInCloudException::authorizationRequired();
        }
        if ($response->status() === 403) {
            throw FattureInCloudException::missingPermission();
        }
        if ($response->status() === 429) {
            throw FattureInCloudException::rateLimited($response->header('Retry-After'));
        }

        throw FattureInCloudException::providerUnavailable();
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload, bool $ambiguousOnConnectionFailure = false): Response
    {
        $response = $this->sendPost($path, $payload, $this->tokens->accessToken(), $ambiguousOnConnectionFailure);
        if ($response->status() === 401) {
            $response = $this->sendPost(
                $path,
                $payload,
                $this->tokens->refreshAccessToken(),
                $ambiguousOnConnectionFailure,
            );
        }
        if ($response->successful()) {
            return $response;
        }
        $rejectionContext = $this->rejectionContext($response);
        $validationFields = $rejectionContext['validation_fields'] ?? [];

        Log::warning('Fatture in Cloud API request was rejected.', [
            'operation' => $this->operation($path),
            'status' => $response->status(),
        ] + $rejectionContext);

        if ($response->status() === 401) {
            throw FattureInCloudException::authorizationRequired();
        }
        if ($response->status() === 403) {
            throw FattureInCloudException::missingPermission();
        }
        if ($response->status() === 429) {
            throw FattureInCloudException::rateLimited($response->header('Retry-After'));
        }
        if ($response->clientError()) {
            throw FattureInCloudException::requestRejected($validationFields);
        }

        throw FattureInCloudException::providerUnavailable();
    }

    /** @param array<string, int|string> $query */
    private function sendGet(string $path, array $query, string $accessToken): Response
    {
        try {
            return Http::acceptJson()
                ->withToken($accessToken)
                ->connectTimeout(10)
                ->timeout(20)
                ->get(FattureInCloudConfiguration::BASE_URL.$path, $query);
        } catch (ConnectionException) {
            Log::warning('Fatture in Cloud API request could not connect.', [
                'operation' => $this->operation($path),
            ]);

            throw FattureInCloudException::providerUnavailable();
        }
    }

    /** @param array<string, mixed> $payload */
    private function sendPost(
        string $path,
        array $payload,
        string $accessToken,
        bool $ambiguousOnConnectionFailure,
    ): Response {
        try {
            return Http::acceptJson()
                ->asJson()
                ->withToken($accessToken)
                ->connectTimeout(10)
                ->timeout(20)
                ->post(FattureInCloudConfiguration::BASE_URL.$path, $payload);
        } catch (ConnectionException) {
            throw $ambiguousOnConnectionFailure
                ? new FattureInCloudAmbiguousOutcome(__('assestme.fatture_in_cloud.errors.ambiguous_outcome'))
                : FattureInCloudException::providerUnavailable();
        }
    }

    /** @return array<string, mixed> */
    private function body(Response $response): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw FattureInCloudException::invalidResponse();
        }

        return $body;
    }

    private function operation(string $path): string
    {
        return match (true) {
            $path === '/user/companies' => 'companies',
            str_ends_with($path, '/info/vat_types') => 'vat_types',
            str_contains($path, '/entities/clients') => 'clients',
            str_contains($path, '/products') => 'products',
            str_contains($path, '/issued_documents') => 'issued_documents',
            default => 'unknown',
        };
    }

    /** @return array{error_code?: string, validation_fields?: list<string>} */
    private function rejectionContext(Response $response): array
    {
        $body = $response->json();
        if (! is_array($body) || ! is_array($body['error'] ?? null)) {
            return [];
        }

        $error = $body['error'];
        $context = [];
        $code = $error['code'] ?? null;
        if (is_string($code) && preg_match('/\A[A-Z0-9_-]{1,80}\z/', $code) === 1) {
            $context['error_code'] = $code;
        }

        $validation = $error['validation_result'] ?? null;
        if (! is_array($validation)) {
            return $context;
        }

        $fields = [];
        foreach (array_keys($validation) as $field) {
            if (! is_string($field) || strlen($field) > 160
                || preg_match('/\A[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\z/', $field) !== 1) {
                continue;
            }
            $fields[] = $field;
            if (count($fields) === 20) {
                break;
            }
        }
        if ($fields !== []) {
            $context['validation_fields'] = $fields;
        }

        return $context;
    }

    private function logInvalidVatResponse(string $reason, int|string|null $index = null): void
    {
        Log::warning('Fatture in Cloud VAT response was invalid.', array_filter([
            'reason' => $reason,
            'item_index' => $index,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function logUnaddressableVatType(string $reason, int|string $index): void
    {
        Log::notice('Fatture in Cloud VAT entry was ignored because it cannot be addressed.', [
            'reason' => $reason,
            'item_index' => $index,
        ]);
    }

    private function identifier(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function parseClient(mixed $value): FattureInCloudClientData
    {
        if (! is_array($value)) {
            throw FattureInCloudException::invalidResponse();
        }
        $id = $this->identifier($value['id'] ?? null);
        $name = $value['name'] ?? null;
        if ($id === null || ! is_string($name) || trim($name) === '') {
            throw FattureInCloudException::invalidResponse();
        }

        return new FattureInCloudClientData(
            id: $id,
            name: trim($name),
            vatNumber: is_string($value['vat_number'] ?? null) ? $value['vat_number'] : null,
            taxCode: is_string($value['tax_code'] ?? null) ? $value['tax_code'] : null,
        );
    }

    private function parseProduct(mixed $value): FattureInCloudProductData
    {
        if (! is_array($value)) {
            throw FattureInCloudException::invalidResponse();
        }
        $id = $this->identifier($value['id'] ?? null);
        $name = $value['name'] ?? null;
        if ($id === null || ! is_string($name) || trim($name) === '') {
            throw FattureInCloudException::invalidResponse();
        }
        $vat = $value['default_vat'] ?? null;

        return new FattureInCloudProductData(
            id: $id,
            code: is_string($value['code'] ?? null) ? $value['code'] : null,
            name: trim($name),
            description: is_string($value['description'] ?? null) ? $value['description'] : null,
            measure: is_string($value['measure'] ?? null) ? $value['measure'] : null,
            netPrice: is_numeric($value['net_price'] ?? null) ? (float) $value['net_price'] : null,
            vatTypeId: is_array($vat) ? $this->identifier($vat['id'] ?? null) : null,
        );
    }

    private function parseDocument(mixed $value): FattureInCloudDocumentData
    {
        if (! is_array($value)) {
            throw FattureInCloudException::invalidResponse();
        }
        $id = $this->identifier($value['id'] ?? null);
        $type = $value['type'] ?? null;
        $subject = $value['subject'] ?? null;
        if ($id === null || ! is_string($type) || ! is_string($subject)) {
            throw FattureInCloudException::invalidResponse();
        }
        $items = $value['items_list'] ?? [];
        if (! is_array($items)) {
            throw FattureInCloudException::invalidResponse();
        }
        $parsedItems = array_map(fn (mixed $item): FattureInCloudDocumentItemData => $this->parseDocumentItem($item), $items);

        return new FattureInCloudDocumentData(
            $id,
            $type,
            $subject,
            is_string($value['visible_subject'] ?? null) ? $value['visible_subject'] : null,
            $this->safeUrl($value['url'] ?? null),
            array_values($parsedItems),
        );
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = parse_url($value, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) ? $value : null;
    }

    private function parseDocumentItem(mixed $value): FattureInCloudDocumentItemData
    {
        if (! is_array($value)) {
            throw FattureInCloudException::invalidResponse();
        }
        $name = $value['name'] ?? null;
        $quantity = $value['qty'] ?? 1;
        $netPrice = $value['net_price'] ?? 0;
        $discount = $value['discount'] ?? 0;
        $vat = $value['vat'] ?? null;
        if (! is_string($name) || ! is_numeric($quantity) || ! is_numeric($netPrice) || ! is_numeric($discount)) {
            throw FattureInCloudException::invalidResponse();
        }

        return new FattureInCloudDocumentItemData(
            productId: $this->identifier($value['product_id'] ?? null),
            code: is_string($value['code'] ?? null) ? $value['code'] : null,
            name: $name,
            description: is_string($value['description'] ?? null) ? $value['description'] : '',
            quantity: (float) $quantity,
            measure: is_string($value['measure'] ?? null) ? $value['measure'] : null,
            netPrice: (float) $netPrice,
            discount: (float) $discount,
            vatTypeId: is_array($vat) ? $this->identifier($vat['id'] ?? null) : null,
        );
    }

    /** @param array<string, mixed> $body */
    private function lastPage(array $body, int $currentPage): int
    {
        $meta = $body['meta'] ?? null;
        $pagination = is_array($meta) ? ($meta['pagination'] ?? null) : null;
        $lastPage = is_array($pagination) ? ($pagination['last_page'] ?? null) : null;
        if ($lastPage === null) {
            return $currentPage;
        }
        if (! is_numeric($lastPage) || (int) $lastPage < $currentPage) {
            throw FattureInCloudException::invalidResponse();
        }

        return (int) $lastPage;
    }
}
