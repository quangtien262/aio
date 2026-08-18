<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

class AccountingApiContractTest extends TestCase
{
    public function test_versioned_openapi_contract_and_sanitized_provider_fixtures_are_valid(): void
    {
        $contract = $this->jsonFile('docs/api/accounting-tax-v1.openapi.json');

        $this->assertSame('3.1.0', $contract['openapi']);
        $this->assertSame('1.0.0', $contract['info']['version']);
        $this->assertSame('gated', $contract['x-provider-contract']['state']);

        foreach ($this->references($contract) as $reference) {
            $this->assertStringStartsWith('#/', $reference);
            $this->assertNotNull($this->resolveReference($contract, $reference), "OpenAPI reference không tồn tại: {$reference}");
        }

        $routes = collect(app('router')->getRoutes()->getRoutes());
        $verbs = ['get', 'post', 'put', 'patch', 'delete'];

        foreach ($contract['paths'] as $path => $pathItem) {
            $uri = 'admin/api/accounting-tax/'.ltrim($path, '/');

            foreach ($verbs as $verb) {
                if (! isset($pathItem[$verb])) {
                    continue;
                }

                /** @var Route|null $route */
                $route = $routes->first(fn (Route $candidate): bool => $candidate->uri() === $uri
                    && in_array(strtoupper($verb), $candidate->methods(), true));
                $this->assertNotNull($route, "OpenAPI operation không có route runtime: {$verb} {$uri}");

                foreach ((array) ($pathItem[$verb]['x-permission'] ?? []) as $permissionWithScope) {
                    $permission = explode('@', $permissionWithScope, 2)[0];
                    $this->assertTrue(
                        collect($route->gatherMiddleware())->contains(
                            fn (string $middleware): bool => str_starts_with($middleware, "admin.permission:{$permission}"),
                        ),
                        "OpenAPI permission không khớp middleware: {$verb} {$uri} {$permission}",
                    );
                }
            }
        }

        $inbound = $this->jsonFile('tests/Fixtures/minvoice/msmi-invoices-page.json');
        $outbound = $this->jsonFile('tests/Fixtures/minvoice/outbound-create-draft-response.json');

        $this->assertSame('mongo-001', $inbound['listInvoice'][0]['_id']);
        $this->assertSame('provider-doc-100', $outbound['hoadon68_id']);
        $this->assertStringNotContainsString('password', json_encode([$inbound, $outbound], JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('token', json_encode([$inbound, $outbound], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function jsonFile(string $path): array
    {
        $contents = file_get_contents(base_path($path));
        $this->assertNotFalse($contents, "Không đọc được JSON contract/fixture: {$path}");

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function references(array $value): array
    {
        $references = [];
        array_walk_recursive($value, function (mixed $item, string|int $key) use (&$references): void {
            if ($key === '$ref' && is_string($item)) {
                $references[] = $item;
            }
        });

        return array_values(array_unique($references));
    }

    private function resolveReference(array $document, string $reference): mixed
    {
        $value = $document;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
