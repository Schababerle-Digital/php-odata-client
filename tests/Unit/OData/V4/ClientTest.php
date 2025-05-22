<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\OData\V4;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use SchababerleDigital\OData\Contract\HttpClientInterface;
use SchababerleDigital\OData\Contract\ResponseParserInterface;
use SchababerleDigital\OData\Contract\SerializerInterface;
use SchababerleDigital\OData\Client\V4\Client as V4Client;
use SchababerleDigital\OData\Client\V4\QueryBuilder as V4QueryBuilder;
use SchababerleDigital\OData\Client\Common\Entity;
use SchababerleDigital\OData\Client\Common\AbstractClient;
use SchababerleDigital\OData\Client\Common\AbstractResponseParser;


/**
 * @covers \SchababerleDigital\OData\Client\V4\Client
 * @covers \SchababerleDigital\OData\Client\Common\AbstractClient
 */
class ClientTest extends TestCase
{
    private HttpClientInterface $mockHttpClient;
    private ResponseParserInterface $mockResponseParser;
    private SerializerInterface $mockSerializer;
    private V4Client $v4Client;
    private string $baseServiceUrl = 'http://localhost/odata/v4/service';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->mockResponseParser = $this->createMock(ResponseParserInterface::class);
        $this->mockSerializer = $this->createMock(SerializerInterface::class);

        $this->v4Client = new V4Client(
            $this->mockHttpClient,
            $this->mockResponseParser,
            $this->mockSerializer,
            $this->baseServiceUrl
        );
    }

    /**
     * @test
     */
    public function getODataVersionHeaderReturnsCorrectV4Headers(): void
    {
        $reflection = new \ReflectionClass(V4Client::class);
        $method = $reflection->getMethod('getODataVersionHeader');
        $method->setAccessible(true);

        $headers = $method->invoke($this->v4Client);
        $this->assertEquals(['Client-Version' => '4.1'], $headers);
    }

    /**
     * @test
     */
    public function createQueryBuilderReturnsV4QueryBuilderInstance(): void
    {
        $queryBuilder = $this->v4Client->createQueryBuilder('Products');
        $this->assertInstanceOf(V4QueryBuilder::class, $queryBuilder);
    }

    /**
     * @test
     */
    public function mergeMethodUsesPatchHttpVerbByDefaultFromAbstractClient(): void
    {
        // AbstractClient's merge implementation already uses PATCH.
        // This test confirms the V4 client inherits this behavior.
        $entitySet = 'Items';
        $id = 'item-1';
        $data = ['Description' => 'Updated item description'];
        $eTag = 'W/"etag123"';
        $expectedUrl = $this->baseServiceUrl . '/' . $entitySet . "('" . $id . "')";
        $serializedData = '{"Description":"Updated item description"}';
        $contentType = 'application/json';

        $this->mockSerializer->method('serializeEntity')->willReturn($serializedData);
        $this->mockSerializer->method('getContentType')->willReturn($contentType);

        $mockPsrResponse = $this->createMock(PsrResponseInterface::class);
        $mockPsrResponse->method('getStatusCode')->willReturn(204);
        $mockPsrResponse->method('getHeaderLine')->willReturnMap([
            ['ETag', $eTag]
        ]);

        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'PATCH', // V4 uses PATCH for merge operations
                $expectedUrl,
                $this->callback(function ($options) use ($contentType, $eTag, $serializedData) {
                    return isset($options['headers']['Content-Type']) && $options['headers']['Content-Type'] === $contentType &&
                        isset($options['headers']['If-Match']) && $options['headers']['If-Match'] === $eTag &&
                        isset($options['body']) && $options['body'] === $serializedData;
                })
            )
            ->willReturn($mockPsrResponse);

        $resultEntity = $this->v4Client->merge($entitySet, $id, $data, $eTag);
        $this->assertInstanceOf(Entity::class, $resultEntity);
        $this->assertEquals($id, $resultEntity->getId());
        $this->assertEquals($eTag, $resultEntity->getETag());
    }

    /**
     * @test
     */
    public function callFunctionWithInlineParametersBuildsUrlCorrectly(): void
    {
        $functionName = 'GetSalesTaxRate';
        // V4 allows parameters inline: Func(Param1=value1,Param2=value2)
        $parameters = ['state' => 'CA', 'zipCode' => 90210]; // Will be formatted
        $expectedPath = $functionName . "(state='CA',zipCode=90210)";
        $expectedUrl = $this->baseServiceUrl . '/' . $expectedPath;

        $mockHttpResponse = new GuzzleResponse(200, [], '{"@odata.context":".../$metadata#Edm.Decimal","value":0.0875}');
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', $expectedUrl, $this->anything())
            ->willReturn($mockHttpResponse);

        $this->mockResponseParser->method('parseValue')->willReturn(0.0875);
        $this->v4Client->callFunction($functionName, $parameters);
    }

    /**
     * @test
     */
    public function callBoundFunctionConstructsUrlCorrectly(): void
    {
        $entitySet = "Products";
        $entityId = 1;
        $functionName = "NS.MostRecentOrder"; // V4 fully qualified
        // Parameters here would go to query string for GET after function
        $parameters = ['maxAmount' => 100];
        $expectedPath = "Products(1)/NS.MostRecentOrder?maxAmount=100";
        $expectedUrl = $this->baseServiceUrl . '/' . $expectedPath;

        $mockHttpResponse = new GuzzleResponse(200, [], '{"@odata.context":".../$metadata#Orders/$entity", "OrderID":1}');
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', $expectedUrl, $this->anything())
            ->willReturn($mockHttpResponse);

        $this->mockResponseParser->method('parseEntity')->willReturn($this->createMock(Entity::class));
        $this->v4Client->callFunction($functionName, $parameters, $entitySet, $entityId);
    }


    /**
     * @test
     */
    public function callActionSendsJsonBodyWithPost(): void
    {
        $actionName = 'SubmitOrder';
        $parameters = ['orderId' => 123, 'customerNotes' => 'Expedite shipping'];
        $expectedPath = $actionName;
        $expectedUrl = $this->baseServiceUrl . '/' . $expectedPath;
        $expectedBody = json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentType = 'application/json;charset=utf-8';

        $mockHttpResponse = new GuzzleResponse(200, [], '{"@odata.context":".../$metadata#Orders/$entity","ConfirmationNumber":"C123"}');
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $expectedUrl,
                $this->callback(function ($options) use ($contentType, $expectedBody) {
                    return isset($options['headers']['Content-Type']) && $options['headers']['Content-Type'] === $contentType &&
                        isset($options['body']) && $options['body'] === $expectedBody;
                })
            )
            ->willReturn($mockHttpResponse);

        $this->mockResponseParser->method('parseEntity')->willReturn($this->createMock(Entity::class));
        $this->v4Client->callAction($actionName, $parameters);
    }

    /**
     * @test
     */
    public function executeBatchSendsCorrectJsonBatchPayload(): void
    {
        $requests = [
            ['id' => 'r1', 'method' => 'GET', 'url' => 'Products'],
            ['id' => 'r2', 'method' => 'POST', 'url' => 'Products', 'body' => ['Name' => 'Test Product']],
        ];

        $expectedBatchBodyStructure = [
            'requests' => [
                [
                    'id' => 'r1',
                    'method' => 'GET',
                    'url' => 'Products',
                    // Passe diesen Header an das an, was getODataVersionHeader() in V4\Client zurückgibt
                    'headers' => ['Client-Version' => '4.1', 'Accept' => 'application/json;odata.metadata=minimal']
                ],
                [
                    'id' => 'r2',
                    'method' => 'POST',
                    'url' => 'Products',
                    // Passe diesen Header an
                    'headers' => ['Client-Version' => '4.1', 'Accept' => 'application/json;odata.metadata=minimal', 'Content-Type' => 'application/json;charset=utf-8'],
                    'body' => ['Name' => 'Test Product']
                ],
            ]
        ];
        $expectedJsonBatchBody = json_encode($expectedBatchBodyStructure, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $mockHttpResponse = new GuzzleResponse(200, [], json_encode(['responses' => []]));
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->baseServiceUrl . '/$batch',
                $this->callback(function($options) use ($expectedJsonBatchBody) {
                    return isset($options['headers']['Content-Type']) &&
                        str_contains($options['headers']['Content-Type'], 'application/json') &&
                        isset($options['body']) && $options['body'] === $expectedJsonBatchBody;
                })
            )
            ->willReturn($mockHttpResponse);

        $this->v4Client->executeBatch($requests);
    }
}