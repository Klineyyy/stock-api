<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_sends_you_to_the_docs(): void
    {
        $this->get('/')->assertRedirect('/docs/api');
    }

    public function test_the_swagger_page_is_public(): void
    {
        $this->get('/docs/api')->assertOk();
    }

    public function test_the_openapi_document_describes_every_endpoint_and_the_bearer_scheme(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertSame('Stock API', $spec['info']['title']);
        $this->assertSame('bearer', $spec['components']['securitySchemes']['http']['scheme'] ?? null);

        $operations = collect($spec['paths'])->flatMap(fn ($methods, $path) => collect($methods)->keys()->map(fn ($m) => strtoupper($m).' '.$path))->all();

        foreach ([
            'POST /v1/auth/login', 'POST /v1/auth/register', 'GET /v1/auth/me', 'GET /v1/lookup', 'GET /v1/summary',
            'GET /v1/stock', 'GET /v1/stock/low', 'POST /v1/stock/adjust', 'PUT /v1/stock/reorder', 'GET /v1/movements',
        ] as $expected) {
            $this->assertContains($expected, $operations);
        }
    }
}
