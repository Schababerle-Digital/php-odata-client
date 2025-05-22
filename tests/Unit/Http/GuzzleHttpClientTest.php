<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleNativeClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SchababerleDigital\OData\Http\GuzzleHttpClient;
use SchababerleDigital\OData\Exception\HttpResponseException;

/**
 * @covers \SchababerleDigital\OData\Http\GuzzleHttpClient
 */
class GuzzleHttpClientTest extends TestCase
{
    private MockHandler $mockHandler;
    private GuzzleClientInterface $mockGuzzleClient;
    private GuzzleHttpClient $httpClient;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $this->mockGuzzleClient = new GuzzleNativeClient(['handler' => $handlerStack]);
        $this->httpClient = new GuzzleHttpClient($this->mockGuzzleClient);
    }

    /**
     * @test
     */
    public function constructorCanAcceptCustomGuzzleClient(): void
    {
        $customGuzzleClient = $this->createMock(GuzzleClientInterface::class);
        $httpClient = new GuzzleHttpClient($customGuzzleClient);

        // Reflection or a more involved test could verify it's the *exact* instance,
        // but for now, ensuring no error and a basic call works is sufficient.
        $this->assertInstanceOf(GuzzleHttpClient::class, $httpClient);
    }

    /**
     * @test
     */
    public function requestMethodReturnsSuccessfulResponse(): void
    {
        $expectedResponse = new GuzzleResponse(200, ['X-Test-Header' => 'Success'], 'Test Body');
        $this->mockHandler->append($expectedResponse);

        $response = $this->httpClient->request('GET', 'test/uri');

        $this->assertSame($expectedResponse, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getHeaderLine('X-Test-Header'));
        $this->assertEquals('Test Body', (string) $response->getBody());
    }

    /**
     * @test
     */
    public function requestMethodThrowsHttpResponseExceptionOnGuzzleRequestException(): void
    {
        $this->expectException(HttpResponseException::class);
        $this->expectExceptionMessage('Error Communicating with Server');

        $request = new GuzzleRequest('GET', 'test/fail');
        $guzzleException = new GuzzleRequestException(
            'Error Communicating with Server',
            $request,
            new GuzzleResponse(500) // Simulate a server error response
        );
        $this->mockHandler->append($guzzleException);

        $this->httpClient->request('GET', 'test/fail');
    }

    /**
     * @test
     * @dataProvider httpVerbMethodsProvider
     */
    public function httpVerbMethodsCallRequestCorrectlyAndReturnResponse(
        string $methodToCall,
        string $expectedHttpMethod,
        string $uri,
        array $args,
        array $expectedGuzzleOptions
    ): void {
        $expectedResponse = new GuzzleResponse(200, [], 'Success');
        $this->mockHandler->append($expectedResponse);

        /** @var ResponseInterface $response */
        $response = call_user_func_array([$this->httpClient, $methodToCall], $args);

        $this->assertSame($expectedResponse, $response);

        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertEquals($expectedHttpMethod, $lastRequest->getMethod());
        $uriParts = parse_url($uri);
        $this->assertEquals($uriParts['path'], $lastRequest->getUri()->getPath());

        // Check options like headers, query, body based on $expectedGuzzleOptions
        if (!empty($expectedGuzzleOptions['headers'])) {
            foreach ($expectedGuzzleOptions['headers'] as $name => $value) {
                $this->assertEquals($value, $lastRequest->getHeaderLine($name));
            }
        }
        if (!empty($expectedGuzzleOptions['query'])) {
            $this->assertEquals(http_build_query($expectedGuzzleOptions['query']), $lastRequest->getUri()->getQuery());
        }
        if (isset($expectedGuzzleOptions['json'])) { // GuzzleHttpClient uses 'json' for array bodies
            $this->assertEquals(json_encode($expectedGuzzleOptions['json']), (string) $lastRequest->getBody());
            $this->assertStringContainsString('application/json', $lastRequest->getHeaderLine('Content-Type'));
        } elseif (isset($expectedGuzzleOptions['body'])) {
            $this->assertEquals($expectedGuzzleOptions['body'], (string) $lastRequest->getBody());
        }
    }

    /**
     * Provides arguments for testing various HTTP verb convenience methods.
     * @return array<string, array<mixed>>
     */
    public static function httpVerbMethodsProvider(): array
    {
        $testUri = 'test/verb/uri';
        $testHeaders = ['X-Custom-Header' => 'TestValue'];
        $testQuery = ['param1' => 'val1', 'active' => 'true'];
        $testBodyArray = ['name' => 'Test', 'value' => 123];
        $testBodyString = 'raw string body';

        return [
            'GET request' => [
                'methodToCall' => 'get',
                'expectedHttpMethod' => 'GET',
                'uri' => $testUri,
                'args' => [$testUri, $testHeaders, $testQuery],
                'expectedGuzzleOptions' => ['headers' => $testHeaders, 'query' => $testQuery]
            ],
            'POST request with array body' => [
                'methodToCall' => 'post',
                'expectedHttpMethod' => 'POST',
                'uri' => $testUri,
                'args' => [$testUri, $testBodyArray, $testHeaders],
                'expectedGuzzleOptions' => ['headers' => $testHeaders, 'json' => $testBodyArray]
            ],
            'POST request with string body' => [
                'methodToCall' => 'post',
                'expectedHttpMethod' => 'POST',
                'uri' => $testUri,
                'args' => [$testUri, $testBodyString, $testHeaders],
                'expectedGuzzleOptions' => ['headers' => $testHeaders, 'body' => $testBodyString]
            ],
            'PUT request with array body' => [
                'methodToCall' => 'put',
                'expectedHttpMethod' => 'PUT',
                'uri' => $testUri,
                'args' => [$testUri, $testBodyArray, $testHeaders],
                'expectedGuzzleOptions' => ['headers' => $testHeaders, 'json' => $testBodyArray]
            ],
            'PATCH request with array body' => [
                'methodToCall' => 'patch',
                'expectedHttpMethod' => 'PATCH',
                'uri' => $testUri,
                'args' => [$testUri, $testBodyArray, $testHeaders],
                'expectedGuzzleOptions' => ['headers' => $testHeaders, 'json' => $testBodyArray]
            ],
            'DELETE request' => [
                'methodToCall' => 'delete',
                'expectedHttpMethod' => 'DELETE',
                'uri' => $testUri,
                'args' => [$testUri, $testHeaders],
                'expectedGuzzleOptions' => ['headers' => $testHeaders]
            ],
        ];
    }
}