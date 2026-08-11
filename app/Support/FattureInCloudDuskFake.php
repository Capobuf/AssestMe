<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

final class FattureInCloudDuskFake
{
    public function install(): void
    {
        Http::fake(static function (Request $request) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/info/vat_types')) {
                return Http::response(['data' => [[
                    'id' => 22,
                    'value' => 22,
                    'description' => 'Ordinaria',
                    'is_disabled' => false,
                    'default' => true,
                ]]], 200);
            }
            if ($request->method() === 'GET' && str_contains($url, '/entities/clients/91')) {
                return Http::response(['data' => [
                    'id' => 91,
                    'name' => 'Cliente browser FIC',
                    'vat_number' => '01234567890',
                    'tax_code' => null,
                ]], 200);
            }
            if ($request->method() === 'GET' && str_contains($url, '/issued_documents')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['pagination' => ['last_page' => 1]],
                ], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/issued_documents')) {
                return Http::response(['data' => [
                    'id' => 901,
                    'type' => 'quote',
                    'subject' => (string) $request['data']['subject'],
                    'visible_subject' => (string) $request['data']['visible_subject'],
                    'url' => 'https://example.test/quotes/901',
                    'items_list' => $request['data']['items_list'],
                ]], 200);
            }

            return Http::response([], 500);
        });
    }
}
