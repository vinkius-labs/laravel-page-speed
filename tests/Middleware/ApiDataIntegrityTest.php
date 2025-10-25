<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders;

/**
 * Critical tests to ensure API middlewares NEVER modify response data
 * This is the most important test suite - data integrity is non-negotiable
 */
class ApiDataIntegrityTest extends TestCase
{
    protected function getMiddleware()
    {
        // Not using a single middleware for this test suite
        $this->middleware = null;
    }

    public function setUp(): void
    {
        parent::setUp();
        // Use array cache driver for tests (no Redis needed)
        config(['laravel-page-speed.api.cache.driver' => 'array']);
    }


    public function test_never_modifies_simple_json_data()
    {
        $originalData = ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com'];
        $request = Request::create('/api/test', 'GET');

        $middleware = new ApiResponseCompression();
        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $this->assertEquals($originalData, json_decode($decompressed, true));
    }


    public function test_never_modifies_nested_json_structures()
    {
        $originalData = [
            'user' => [
                'id' => 1,
                'profile' => [
                    'name' => 'John',
                    'settings' => [
                        'theme' => 'dark',
                        'notifications' => true
                    ]
                ]
            ],
            'meta' => ['version' => '1.0.0']
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $this->assertEquals($originalData, json_decode($decompressed, true));
    }


    public function test_never_modifies_array_of_objects()
    {
        $originalData = [
            ['id' => 1, 'name' => 'Item 1', 'price' => 99.99],
            ['id' => 2, 'name' => 'Item 2', 'price' => 149.50],
            ['id' => 3, 'name' => 'Item 3', 'price' => 299.00],
        ];

        $request = Request::create('/api/products', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $this->assertEquals($originalData, json_decode($decompressed, true));
    }


    public function test_preserves_special_characters_in_json()
    {
        $originalData = [
            'name' => 'José María',
            'address' => '123 Rua São Paulo, São José dos Campos',
            'description' => 'Special chars: áéíóú ÁÉÍÓÚ àèìòù ñÑ ç',
            'unicode' => '你好世界 🚀 emoji test',
            'html' => '<script>alert("test")</script>',
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertEquals($originalData['name'], $decoded['name']);
        $this->assertEquals($originalData['address'], $decoded['address']);
        $this->assertEquals($originalData['description'], $decoded['description']);
        $this->assertEquals($originalData['unicode'], $decoded['unicode']);
        $this->assertEquals($originalData['html'], $decoded['html']);
    }


    public function test_preserves_numeric_precision()
    {
        $originalData = [
            'integer' => 999999999999,
            'float' => 123.456789,
            'scientific' => 1.23e-10,
            'negative' => -99.99,
            'zero' => 0,
            'price' => 1234.56,
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertEquals($originalData['integer'], $decoded['integer']);
        $this->assertEquals($originalData['float'], $decoded['float']);
        $this->assertEquals($originalData['price'], $decoded['price']);
        $this->assertEquals($originalData['zero'], $decoded['zero']);
    }


    public function test_preserves_boolean_and_null_values()
    {
        $originalData = [
            'active' => true,
            'disabled' => false,
            'nullable' => null,
            'empty_string' => '',
            'empty_array' => [],
            'empty_object' => new \stdClass(),
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertTrue($decoded['active']);
        $this->assertFalse($decoded['disabled']);
        $this->assertNull($decoded['nullable']);
        $this->assertEquals('', $decoded['empty_string']);
        $this->assertEquals([], $decoded['empty_array']);
    }


    public function test_never_modifies_data_with_multiple_middlewares()
    {
        $originalData = [
            'id' => 123,
            'name' => 'Test Product',
            'price' => 99.99,
            'metadata' => ['color' => 'red', 'size' => 'M']
        ];

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        // Stack all API middlewares
        $compression = new ApiResponseCompression();
        $etag = new ApiETag();
        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();

        $response = $compression->handle($request, function () use ($etag, $security, $performance, $originalData, $request) {
            return $etag->handle($request, function () use ($security, $performance, $originalData, $request) {
                return $security->handle($request, function () use ($performance, $originalData, $request) {
                    return $performance->handle($request, function () use ($originalData) {
                        return new JsonResponse($originalData);
                    });
                });
            });
        });

        $decompressed = $this->decompressResponse($response);
        $this->assertEquals($originalData, json_decode($decompressed, true));
    }


    public function test_preserves_json_with_cache_middleware()
    {
        config(['laravel-page-speed.api.cache.enabled' => true]);

        $originalData = ['id' => 1, 'data' => 'test', 'timestamp' => time()];
        $request = Request::create('/api/test', 'GET');

        $middleware = new ApiResponseCache();

        // First request (cache miss)
        $response1 = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        // Second request (cache hit)
        $response2 = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $this->assertEquals($originalData, json_decode($response1->getContent(), true));
        $this->assertEquals($originalData, json_decode($response2->getContent(), true));
    }


    public function test_handles_empty_responses_safely()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse([]);
        });

        $this->assertEquals([], json_decode($response->getContent(), true));
    }


    public function test_handles_large_json_arrays_without_corruption()
    {
        // Generate large dataset
        $originalData = [];
        for ($i = 0; $i < 1000; $i++) {
            $originalData[] = [
                'id' => $i,
                'name' => "Item {$i}",
                'price' => rand(100, 9999) / 100,
                'description' => str_repeat("Description {$i} ", 10),
            ];
        }

        $request = Request::create('/api/products', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertCount(1000, $decoded);
        $this->assertEquals($originalData[0], $decoded[0]);
        $this->assertEquals($originalData[999], $decoded[999]);
    }


    public function test_preserves_json_with_quotes_and_escape_sequences()
    {
        $originalData = [
            'quote' => 'He said "Hello"',
            'single_quote' => "It's working",
            'backslash' => 'Path: C:\\Users\\test',
            'newline' => "Line 1\nLine 2",
            'tab' => "Col1\tCol2",
            'json_string' => '{"nested":"json"}',
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertEquals($originalData['quote'], $decoded['quote']);
        $this->assertEquals($originalData['single_quote'], $decoded['single_quote']);
        $this->assertEquals($originalData['backslash'], $decoded['backslash']);
        $this->assertEquals($originalData['json_string'], $decoded['json_string']);
    }


    public function test_handles_deeply_nested_structures()
    {
        $originalData = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => [
                                'data' => 'Deep value'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertEquals(
            $originalData['level1']['level2']['level3']['level4']['level5']['data'],
            $decoded['level1']['level2']['level3']['level4']['level5']['data']
        );
    }


    public function test_preserves_dates_and_timestamps()
    {
        $originalData = [
            'created_at' => '2024-01-15T10:30:00Z',
            'updated_at' => '2024-01-15T15:45:30.123456Z',
            'timestamp' => 1705318200,
            'date' => '2024-01-15',
        ];

        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () use ($originalData) {
            return new JsonResponse($originalData);
        });

        $decompressed = $this->decompressResponse($response);
        $decoded = json_decode($decompressed, true);

        $this->assertEquals($originalData['created_at'], $decoded['created_at']);
        $this->assertEquals($originalData['updated_at'], $decoded['updated_at']);
        $this->assertEquals($originalData['timestamp'], $decoded['timestamp']);
        $this->assertEquals($originalData['date'], $decoded['date']);
    }

    /**
     * Helper to decompress response content
     */
    private function decompressResponse($response): string
    {
        $content = $response->getContent();
        $encoding = $response->headers->get('Content-Encoding');

        if ($encoding === 'gzip') {
            return gzdecode($content);
        }

        if ($encoding === 'br' && function_exists('brotli_uncompress')) {
            return brotli_uncompress($content);
        }

        return $content;
    }
}
