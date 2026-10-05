<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

// MD §4: "Document this fully with OpenAPI/Postman before Flutter work starts". Keeps docs/openapi.json in sync with routes.
class OpenApiDocTest extends TestCase
{
    public function test_every_api_route_is_documented_and_nothing_extra(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('../docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('3.1.0', $spec['openapi']);

        $documented = collect($spec['paths'])->flatMap(fn (array $ops, string $path) => collect(array_keys($ops))->map(fn ($m) => strtoupper($m).' '.$path))->sort()->values();

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => Str::startsWith($r->uri(), 'api/v1/'))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')
                ->map(fn ($m) => $m.' /'.Str::after($r->uri(), 'api/v1/')))
            ->map(fn (string $route) => preg_replace('/\{(\w+)\?\}/', '{$1}', $route))
            ->sort()->values();

        $this->assertSame($routes->all(), $documented->all());
    }

    public function test_every_schema_reference_resolves(): void
    {
        $raw = (string) file_get_contents(base_path('../docs/openapi.json'));
        $spec = json_decode($raw, true);
        preg_match_all('~"#/components/(\w+)/(\w+)"~', $raw, $refs, PREG_SET_ORDER);

        foreach ($refs as [, $section, $name]) {
            $this->assertArrayHasKey($name, $spec['components'][$section] ?? [], "#/components/$section/$name");
        }
    }
}
