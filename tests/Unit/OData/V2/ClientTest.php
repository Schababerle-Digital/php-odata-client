<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\OData\V2;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use SchababerleDigital\OData\Contract\HttpClientInterface;
use SchababerleDigital\OData\Contract\ResponseParserInterface;
use SchababerleDigital\OData\Contract\SerializerInterface;
use SchababerleDigital\OData\Client\V2\Client as V2Client;
use SchababerleDigital\OData\Client\V2\QueryBuilder as V2QueryBuilder;
use SchababerleDigital\OData\Exception\ODataRequestException;
use SchababerleDigital\OData\Client\Common\Entity;
use SchababerleDigital\OData\Client\Common\AbstractClient;

/**
 * @covers \SchababerleDigital\OData\Client\V2\Client
 * @covers \SchababerleDigital\OData\Client\Common\AbstractClient
 */
class ClientTest extends TestCase
{
    private HttpClientInterface $mockHttpClient;
    private ResponseParserInterface $mockResponseParser;
    private SerializerInterface $mockSerializer;
    private V2Client $v2Client;
    private string $baseServiceUrl = 'http://localhost/odata.svc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->mockResponseParser = $this->createMock(ResponseParserInterface::class);
        $this->mockSerializer = $this->createMock(SerializerInterface::class);

        $this->v2Client = new V2Client(
            $this->mockHttpClient,
            $this->mockResponseParser,
            $this->mockSerializer,
            $this->baseServiceUrl
        );
    }

    /**
     * @test
     */
    public function getODataVersionHeaderReturnsCorrectV2Headers(): void
    {
        // Access protected method using reflection for testing its specific output
        $reflection = new \ReflectionClass(V2Client::class); // Test the concrete implementation via parent
        $method = $reflection->getMethod('getODataVersionHeader');
        $method->setAccessible(true); // Make it accessible

        $headers = $method->invoke($this->v2Client);

        $this->assertEquals([
            'Client-Version' => '2.0',
            'MaxDataServiceVersion' => '2.0',
        ], $headers);
    }

    /**
     * @test
     */
    public function createQueryBuilderReturnsV2QueryBuilderInstance(): void
    {
        $queryBuilder = $this->v2Client->createQueryBuilder('TestSet');
        $this->assertInstanceOf(V2QueryBuilder::class, $queryBuilder);
    }

    /**
     * @test
     */
    public function mergeMethodUsesMergeHttpVerb(): void
    {
        $entitySet = 'Products';
        $id = '1';
        $data = ['Name' => 'Updated Product'];
        $eTag = 'W/"123"';
        $expectedUrl = $this->baseServiceUrl . '/' . $entitySet . "('" . $id . "')";
        $serializedData = '{"Name":"Updated Product"}';
        $contentType = 'application/json';

        $this->mockSerializer->method('serializeEntity')->willReturn($serializedData);
        $this->mockSerializer->method('getContentType')->willReturn($contentType);

        $mockPsrResponse = $this->createMock(PsrResponseInterface::class);
        $mockPsrResponse->method('getStatusCode')->willReturn(204); // MERGE often returns 204
        $mockPsrResponse->method('getHeaderLine')->willReturnMap([
            ['ETag', $eTag]
        ]);


        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'MERGE', // V2 Client should use MERGE
                $expectedUrl,
                $this->callback(function ($options) use ($contentType, $eTag, $serializedData) {
                    return isset($options['headers']['Content-Type']) && $options['headers']['Content-Type'] === $contentType &&
                        isset($options['headers']['If-Match']) && $options['headers']['If-Match'] === $eTag &&
                        isset($options['body']) && $options['body'] === $serializedData;
                })
            )
            ->willReturn($mockPsrResponse);

        // This part is a bit tricky as parseEntity is called for 204 if it's an EntityInterface input.
        // Our input here is an array, so AbstractClient::createBareEntity will be called.
        // Or it might attempt to parse response if not 204.
        // Since it's 204, it will call createBareEntity.

        $resultEntity = $this->v2Client->merge($entitySet, $id, $data, $eTag);
        $this->assertInstanceOf(Entity::class, $resultEntity); // from createBareEntity
        $this->assertEquals($id, $resultEntity->getId());
        $this->assertEquals($eTag, $resultEntity->getETag());
        $this->assertFalse($resultEntity->isNew());
    }

    /**
     * @test
     */
    public function executeBatchThrowsODataRequestException(): void
    {
        $this->expectException(ODataRequestException::class);
        $this->expectExceptionMessage('Client V2 batch processing is not yet implemented in this client.');
        $this->v2Client->executeBatch([]);
    }

    /**
     * @test
     */
    public function callFunctionConstructsUrlCorrectlyAndUsesGet(): void
    {
        $functionName = 'GetProductsByRating';
        $parameters = ['rating' => 4];
        $expectedPath = $functionName . '?rating=4'; // V2 service op params often in query
        $expectedUrl = $this->baseServiceUrl . '/' . $expectedPath;

        $mockHttpResponse = new GuzzleResponse(200, [], '{"d":{"results":[]}}'); // Empty collection
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', $expectedUrl, $this->anything())
            ->willReturn($mockHttpResponse);

        $this->mockResponseParser->method('parseCollection')->willReturn($this->createMock(\SchababerleDigital\OData\Contract\EntityCollectionInterface::class));
        // We mock parseCollection because the parsing logic itself is tested in ResponseParserTest
        // Here we test if Client correctly calls it for function returning collection.

        $this->v2Client->callFunction($functionName, $parameters);
    }

    /**
     * @test
     */
    public function callActionConstructsUrlAndBodyCorrectlyAndUsesPost(): void
    {
        $actionName = 'UpdateProductRating';
        $parameters = ['productID' => 1, 'newRating' => 5];
        $expectedPath = $actionName;
        $expectedUrl = $this->baseServiceUrl . '/' . $expectedPath;
        $expectedBody = json_encode($parameters);
        $contentType = 'application/json';

        $mockHttpResponse = new GuzzleResponse(204); // Action might return No Content
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $expectedUrl,
                $this->callback(function ($options) use ($contentType, $expectedBody) {
                    return isset($options['headers']['Content-Type']) &&
                        str_contains($options['headers']['Content-Type'], $contentType) &&
                        isset($options['body']) && $options['body'] === $expectedBody;
                })
            )
            ->willReturn($mockHttpResponse);

        // If response is 204, parseValue/parseEntity/parseCollection won't be called.
        // The result of callAction is true in this case.
        $result = $this->v2Client->callAction($actionName, $parameters);
        $this->assertTrue($result);
    }
}