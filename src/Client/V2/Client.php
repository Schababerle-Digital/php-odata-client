<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\V2;

use Closure;
use SchababerleDigital\OData\Client\Common\AbstractClient;
use SchababerleDigital\OData\Contract\HttpClientInterface;
use SchababerleDigital\OData\Contract\QueryBuilderInterface;
use SchababerleDigital\OData\Contract\ResponseParserInterface;
use SchababerleDigital\OData\Contract\SerializerInterface;
use SchababerleDigital\OData\Exception\ODataRequestException;

/**
 * Client V2 specific client.
 */
class Client extends AbstractClient
{
    protected const ODATA_V2_VERSION_HEADER_VALUE = '2.0';
    // MaxDataServiceVersion is often used in conjunction with Client-Version for V2/V3 compatibility.
    protected const MAX_DATA_SERVICE_VERSION_HEADER_VALUE = '2.0';


    /**
     * @param HttpClientInterface $httpClient
     * @param ResponseParserInterface $responseParser Typically an instance of SchababerleDigital\Client\V2\ResponseParser
     * @param SerializerInterface $serializer
     * @param string $baseServiceUrl
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ResponseParserInterface $responseParser, // Should be V2Parser
        SerializerInterface $serializer, // Consider if a V2Serializer is needed
        string $baseServiceUrl
    ) {
        parent::__construct($httpClient, $responseParser, $serializer, $baseServiceUrl);
    }

    /**
     * {@inheritDoc}
     */
    protected function getODataVersionHeader(): array
    {
        return [
            'Client-Version' => self::ODATA_V2_VERSION_HEADER_VALUE,
            'MaxDataServiceVersion' => self::MAX_DATA_SERVICE_VERSION_HEADER_VALUE,
            // V2 often prefers application/json;odata=verbose for full metadata,
            // or just application/json. AbstractClient uses 'minimal'. This might need adjustment.
            // 'Accept' => 'application/json;odata=verbose',
        ];
    }

    /**
     * {@inheritDoc}
     * Returns a V2 specific QueryBuilder.
     */
    public function createQueryBuilder(?string $entitySet = null): QueryBuilderInterface
    {
        $builder = new QueryBuilder(); // V2 QueryBuilder
        if ($entitySet) {
            $builder->setEntitySet($entitySet);
        }
        return $builder;
    }

    /**
     * {@inheritDoc}
     * In Client V2, partial updates are typically done using MERGE.
     */
    public function merge(string $entitySet, string|int $id, \SchababerleDigital\OData\Contract\EntityInterface|array $data, ?string $eTag = null): \SchababerleDigital\OData\Contract\EntityInterface
    {
        $url = $this->buildUrl($entitySet . '(' . $this->encodeKey($id) . ')');
        $entityToSerialize = ($data instanceof \SchababerleDigital\OData\Contract\EntityInterface) ? $data : $this->arrayToEntity($entitySet, $data, $id);
        $body = $this->serializer->serializeEntity($entityToSerialize);

        $headers = ['Content-Type' => $this->serializer->getContentType()];
        $resolvedETag = $eTag ?? ($entityToSerialize instanceof \SchababerleDigital\OData\Contract\EntityInterface ? $entityToSerialize->getETag() : null);
        if ($resolvedETag) {
            $headers['If-Match'] = $resolvedETag;
        }

        // Use MERGE for Client V2
        $response = $this->executeRequest('MERGE', $url, $headers, $body);
        $responseBodyString = (string)$response->getBody();

        if ($response->getStatusCode() === 204) {
            $newETag = $response->getHeaderLine('ETag') ?: $resolvedETag;
            if ($entityToSerialize instanceof \SchababerleDigital\OData\Contract\EntityInterface) {
                return $entityToSerialize->setETag($newETag)->markAsPersisted($id, $newETag);
            }
            return $this->createBareEntity($entitySet, $id, $newETag);
        }
        try {
            return $this->responseParser->parseEntity($responseBodyString, $this->getResponseHeaders($response));
        } catch (\SchababerleDigital\OData\Exception\ParseException $e) {
            throw new ODataRequestException("Failed to parse updated entity from MERGE response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     * Client V2 refers to these as Service Operations.
     * This is a simplified implementation; V2 service operations might have different URL conventions
     * or parameter passing mechanisms (e.g. all in query string for GET).
     */
    public function callFunction(
        string $functionName,
        array $parameters = [],
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null,
        ?Closure $queryConfigurator = null
    ): mixed {
        $path = '';
        if ($bindingEntitySet) {
            $path .= $bindingEntitySet;
            if ($bindingEntityId !== null) {
                $path .= '(' . $this->encodeKey($bindingEntityId) . ')';
            }
            $path .= '/';
        }
        $path .= $functionName;

        $queryBuilder = $this->createQueryBuilder(null); // No specific entity set for function call query
        // Add function parameters to query builder for GET request
        foreach($parameters as $key => $value) {
            $queryBuilder->custom($key, (string) $value); // V2 service op parameters are often in query
        }

        if ($queryConfigurator !== null) {
            $queryConfigurator($queryBuilder);
        }

        $url = $this->buildUrl($path, $queryBuilder->getQueryParams());
        $response = $this->executeRequest('GET', $url); // Service Operations (functions) are GET
        $responseBody = (string)$response->getBody();

        // Determine what kind of response to parse (entity, collection, value)
        // This is a simplification; might need content type inspection or hints
        try {
            // Attempt to parse as collection first if structure indicates it
            if (str_contains($responseBody, '"' . ResponseParser::V2_RESULTS_PROPERTY . '"')) {
                return $this->responseParser->parseCollection($responseBody, $this->getResponseHeaders($response));
            }
            // Attempt to parse as single entity
            // A V2 single entity often has '__metadata'
            if (str_contains($responseBody, '"' . ResponseParser::V2_METADATA_PROPERTY . '"')) {
                return $this->responseParser->parseEntity($responseBody, $this->getResponseHeaders($response));
            }
            // Fallback to parse as primitive value or complex type
            return $this->responseParser->parseValue($responseBody, $this->getResponseHeaders($response));
        } catch (\SchababerleDigital\OData\Exception\ParseException $e) {
            throw new ODataRequestException("Failed to parse function/service operation response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     * In Client V2, "Actions" are less formally defined than in V4.
     * They are typically Service Operations called with POST.
     */
    public function callAction(
        string $actionName,
        array $parameters = [], // Parameters are typically sent in the body for POST
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null
    ): mixed {
        $path = '';
        if ($bindingEntitySet) {
            $path .= $bindingEntitySet;
            if ($bindingEntityId !== null) {
                $path .= '(' . $this->encodeKey($bindingEntityId) . ')';
            }
            $path .= '/';
        }
        $path .= $actionName;

        $url = $this->buildUrl($path);
        // For POST based service operations, parameters are in the body.
        // The body structure would depend on the specific service operation.
        // Assuming parameters are a flat JSON object for this example.
        $body = empty($parameters) ? null : json_encode($parameters, JSON_THROW_ON_ERROR);
        $headers = [];
        if ($body !== null) {
            $headers['Content-Type'] = self::JSON_CONTENT_TYPE;
        }

        $response = $this->executeRequest('POST', $url, $headers, $body);
        $responseBody = (string)$response->getBody();
        $statusCode = $response->getStatusCode();

        if ($statusCode === 204 || empty(trim($responseBody))) { // No content
            return true; // Or null, or some success indicator
        }

        try {
            // Similar to callFunction, try to intelligently parse response
            if (str_contains($responseBody, '"' . ResponseParser::V2_RESULTS_PROPERTY . '"')) {
                return $this->responseParser->parseCollection($responseBody, $this->getResponseHeaders($response));
            }
            if (str_contains($responseBody, '"' . ResponseParser::V2_METADATA_PROPERTY . '"')) {
                return $this->responseParser->parseEntity($responseBody, $this->getResponseHeaders($response));
            }
            return $this->responseParser->parseValue($responseBody, $this->getResponseHeaders($response));
        } catch (\SchababerleDigital\OData\Exception\ParseException $e) {
            throw new ODataRequestException("Failed to parse action/service operation POST response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     * Batch processing in Client V2 has a specific multipart format.
     * This requires a more complex implementation for constructing the batch request body
     * and parsing the multipart response. This is a placeholder.
     * @throws ODataRequestException Operation not yet implemented.
     */
    public function executeBatch(array $requests): array
    {
        // Implementing V2 batch requests is complex due to multipart/mixed format.
        // It involves creating a specific body structure and parsing a multipart response.
        // Each part of the batch can be a GET, POST, PUT, MERGE, DELETE.
        // Content-ID is used for referencing entities created in the same batch.
        throw new ODataRequestException('Client V2 batch processing is not yet implemented in this client.');
    }
}