<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\Common;

use Closure;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;
use SchababerleDigital\OData\Contract\HttpClientInterface;
use SchababerleDigital\OData\Contract\ODataClientInterface;
use SchababerleDigital\OData\Contract\QueryBuilderInterface;
use SchababerleDigital\OData\Contract\ResponseParserInterface;
use SchababerleDigital\OData\Contract\SerializerInterface;
use SchababerleDigital\OData\Exception\EntityNotFoundException;
use SchababerleDigital\OData\Exception\HttpResponseException;
use SchababerleDigital\OData\Exception\ODataRequestException;
use SchababerleDigital\OData\Exception\ParseException;

/**
 * Provides common functionality for Client clients.
 * Specific Client versions (V2, V4) will extend this class.
 */
abstract class AbstractClient implements ODataClientInterface
{
    protected HttpClientInterface $httpClient;
    protected ResponseParserInterface $responseParser;
    protected SerializerInterface $serializer;
    protected string $baseServiceUrl;

    protected const METADATA_ENDPOINT = '$metadata';
    protected const DEFAULT_HEADERS = [
        'Accept' => 'application/json;odata.metadata=minimal', // Common default
        'Client-Version' => '4.0', // Should be overridden by specific client V2/V4
    ];
    protected const JSON_CONTENT_TYPE = 'application/json';


    /**
     * @param HttpClientInterface $httpClient The HTTP client for making requests.
     * @param ResponseParserInterface $responseParser The parser for Client responses.
     * @param SerializerInterface $serializer The serializer for Client requests.
     * @param string $baseServiceUrl The base URL of the Client service (e.g., "https://services.odata.org/V4/TripPinServiceRW/").
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ResponseParserInterface $responseParser,
        SerializerInterface $serializer,
        string $baseServiceUrl
    ) {
        $this->httpClient = $httpClient;
        $this->responseParser = $responseParser;
        $this->serializer = $serializer;
        $this->baseServiceUrl = rtrim($baseServiceUrl, '/') . '/';
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $entitySet, string|int $id, ?Closure $queryConfigurator = null): EntityInterface
    {
        $queryBuilder = $this->createQueryBuilder($entitySet);
        if ($queryConfigurator !== null) {
            $queryConfigurator($queryBuilder);
        }
        $url = $this->buildUrl($entitySet . '(' . $this->encodeKey($id) . ')', $queryBuilder->getQueryParams());
        $response = $this->executeRequest('GET', $url);

        try {
            return $this->responseParser->parseEntity((string)$response->getBody(), $this->getResponseHeaders($response));
        } catch (ParseException $e) {
            throw new ODataRequestException("Failed to parse entity from response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $entitySet, ?Closure $queryConfigurator = null): EntityCollectionInterface
    {
        $queryBuilder = $this->createQueryBuilder($entitySet);
        if ($queryConfigurator !== null) {
            $queryConfigurator($queryBuilder);
        }
        $url = $this->buildUrl($entitySet, $queryBuilder->getQueryParams());
        $response = $this->executeRequest('GET', $url);

        try {
            return $this->responseParser->parseCollection((string)$response->getBody(), $this->getResponseHeaders($response));
        } catch (ParseException $e) {
            throw new ODataRequestException("Failed to parse collection from response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $entitySet, EntityInterface|array $data): EntityInterface
    {
        $url = $this->buildUrl($entitySet);
        $entityToSerialize = ($data instanceof EntityInterface) ? $data : $this->arrayToEntity($entitySet, $data);
        $body = $this->serializer->serializeEntity($entityToSerialize);
        $headers = ['Content-Type' => $this->serializer->getContentType()];

        $response = $this->executeRequest('POST', $url, $headers, $body);

        try {
            // Client typically returns the created entity, often with a 201 status.
            // Some services might return 204 No Content if 'return=minimal' preference is applied.
            $responseBodyString = (string)$response->getBody();
            if ($response->getStatusCode() === 201 && !empty(trim($responseBodyString))) {
                return $this->responseParser->parseEntity($responseBodyString, $this->getResponseHeaders($response));
            } elseif ($response->getStatusCode() === 204) { // Successfully created but no content returned
                // The input entity might not have server-generated fields (ID, ETag)
                // This part would ideally return the input entity if it was an object,
                // or reconstruct if it was an array, but without server data.
                // For now, we assume $entityToSerialize contains the client-side view.
                // A more robust solution might involve fetching the entity if ID is known/returned in headers.
                if ($entityToSerialize instanceof EntityInterface) {
                    // If an ETag or Location header provides info, update the entity.
                    $eTag = $response->getHeaderLine('ETag') ?: null;
                    $location = $response->getHeaderLine('Location');
                    $newId = null;
                    if ($location) {
                        $newId = $this->extractIdFromLocation($location, $entitySet);
                    }
                    return $entityToSerialize->markAsPersisted($newId ?? $entityToSerialize->getId(), $eTag);
                }
                throw new ODataRequestException("Entity created (204 No Content), but cannot return representation from array input without response body.", $response->getStatusCode());
            }
            return $this->responseParser->parseEntity($responseBodyString, $this->getResponseHeaders($response));
        } catch (ParseException $e) {
            throw new ODataRequestException("Failed to parse created entity from response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $entitySet, string|int $id, EntityInterface|array $data, ?string $eTag = null): EntityInterface
    {
        $url = $this->buildUrl($entitySet . '(' . $this->encodeKey($id) . ')');
        $entityToSerialize = ($data instanceof EntityInterface) ? $data : $this->arrayToEntity($entitySet, $data, $id);
        $body = $this->serializer->serializeEntity($entityToSerialize);

        $headers = ['Content-Type' => $this->serializer->getContentType()];
        $resolvedETag = $eTag ?? ($entityToSerialize instanceof EntityInterface ? $entityToSerialize->getETag() : null);
        if ($resolvedETag) {
            $headers['If-Match'] = $resolvedETag;
        }

        $response = $this->executeRequest('PUT', $url, $headers, $body);
        $responseBodyString = (string)$response->getBody();

        // PUT can return 200 OK with entity or 204 No Content.
        if ($response->getStatusCode() === 204) {
            $newETag = $response->getHeaderLine('ETag') ?: $resolvedETag; // Keep old ETag if none returned? Or null?
            if ($entityToSerialize instanceof EntityInterface) {
                return $entityToSerialize->setETag($newETag)->markAsPersisted($id, $newETag);
            }
            // If original was array, we can't update it meaningfully without a response body.
            // Create a new minimal entity.
            return $this->createBareEntity($entitySet, $id, $newETag);
        }

        try {
            return $this->responseParser->parseEntity($responseBodyString, $this->getResponseHeaders($response));
        } catch (ParseException $e) {
            throw new ODataRequestException("Failed to parse updated entity from PUT response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function merge(string $entitySet, string|int $id, EntityInterface|array $data, ?string $eTag = null): EntityInterface
    {
        $url = $this->buildUrl($entitySet . '(' . $this->encodeKey($id) . ')');
        $entityToSerialize = ($data instanceof EntityInterface) ? $data : $this->arrayToEntity($entitySet, $data, $id);
        $body = $this->serializer->serializeEntity($entityToSerialize); // Serializer should handle partial nature for PATCH

        $headers = ['Content-Type' => $this->serializer->getContentType()];
        $resolvedETag = $eTag ?? ($entityToSerialize instanceof EntityInterface ? $entityToSerialize->getETag() : null);
        if ($resolvedETag) {
            $headers['If-Match'] = $resolvedETag;
        }

        $response = $this->executeRequest('PATCH', $url, $headers, $body); // Or MERGE for Client V2
        $responseBodyString = (string)$response->getBody();

        if ($response->getStatusCode() === 204) {
            $newETag = $response->getHeaderLine('ETag') ?: $resolvedETag;
            if ($entityToSerialize instanceof EntityInterface) {
                // For PATCH, properties not in the request are unchanged on server.
                // The local entity should be updated with new ETag.
                // Ideally, refetch or merge response if available and contains changes.
                return $entityToSerialize->setETag($newETag)->markAsPersisted($id, $newETag);
            }
            return $this->createBareEntity($entitySet, $id, $newETag);
        }
        try {
            return $this->responseParser->parseEntity($responseBodyString, $this->getResponseHeaders($response));
        } catch (ParseException $e) {
            throw new ODataRequestException("Failed to parse updated entity from PATCH/MERGE response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }


    /**
     * {@inheritDoc}
     */
    public function delete(string $entitySet, string|int $id, ?string $eTag = null): bool
    {
        $url = $this->buildUrl($entitySet . '(' . $this->encodeKey($id) . ')');
        $headers = [];
        if ($eTag) {
            $headers['If-Match'] = $eTag;
        }
        $response = $this->executeRequest('DELETE', $url, $headers);
        return $response->getStatusCode() === 204;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadataDocument(): string
    {
        $url = $this->baseServiceUrl . self::METADATA_ENDPOINT;
        $response = $this->executeRequest('GET', $url, ['Accept' => 'application/xml']); // Metadata is usually XML
        return (string)$response->getBody();
    }

    /**
     * {@inheritDoc}
     */
    public function getServiceDocument(): array
    {
        $url = $this->baseServiceUrl; // Service document is at the root
        $response = $this->executeRequest('GET', $url, ['Accept' => self::JSON_CONTENT_TYPE]);
        try {
            $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new ParseException('Service document is not a valid JSON array or object.', (string)$response->getBody());
            }
            return $decoded;
        } catch (JsonException $e) {
            throw new ParseException('Failed to parse service document: ' . $e->getMessage(), (string)$response->getBody(), 0, $e);
        }
    }

    /**
     * Builds a full Client URL.
     * @param string $path The path relative to the base service URL (e.g., "Customers", "Products(1)/Category").
     * @param array<string, mixed> $queryParameters Query parameters to append.
     * @return string The full URL.
     */
    protected function buildUrl(string $path, array $queryParameters = []): string
    {
        $url = $this->baseServiceUrl . ltrim($path, '/');
        if (!empty($queryParameters)) {
            // Ensure system query options ($...) are handled correctly by http_build_query
            // PHP_QUERY_RFC3986 ensures spaces are %20 not +
            $url .= '?' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /**
     * Executes an HTTP request and handles common Client error responses.
     * @param string $method HTTP method.
     * @param string $url Full request URL.
     * @param array<string, string|string[]> $headers Request headers.
     * @param mixed|null $body Request body.
     * @return PsrResponseInterface The successful PSR-7 response.
     * @throws HttpResponseException If the HTTP request fails or returns an error status.
     * @throws EntityNotFoundException If a 404 is returned.
     * @throws ODataRequestException For other Client specific errors.
     */
    protected function executeRequest(string $method, string $url, array $headers = [], mixed $body = null): PsrResponseInterface
    {
        $defaultHeaders = static::DEFAULT_HEADERS;
        // Specific client (V2/V4) should override Client-Version in DEFAULT_HEADERS
        $finalHeaders = array_merge($defaultHeaders, $this->getODataVersionHeader(), $headers);

        $options = ['headers' => $finalHeaders];
        if ($body !== null) {
            $options['body'] = $body;
        }

        $response = $this->httpClient->request($method, $url, $options);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return $response;
        }

        $errorData = $this->responseParser->parseError((string)$response->getBody(), $statusCode, $this->getResponseHeaders($response));

        if ($statusCode === 404) {
            throw new EntityNotFoundException(
                $errorData['message'] ?? 'Entity not found.',
                null, // entitySet - could be parsed from URL if needed
                null, // entityId - could be parsed from URL if needed
                null, // request - not easily available here without more complex setup
                $response,
                null,
                $errorData
            );
        }

        throw new HttpResponseException(
            $errorData['message'] ?? 'HTTP request failed.',
            null, // request
            $response,
            null,
            $errorData
        );
    }

    /**
     * Encodes a key value for use in a URL path segment.
     * Handles strings by quoting them if necessary.
     * @param string|int $key
     * @return string|int
     */
    protected function encodeKey(string|int $key): string|int
    {
        if (is_string($key)) {
            // Client string keys in URLs are typically enclosed in single quotes.
            // And single quotes within the string key itself are doubled.
            return "'" . str_replace("'", "''", $key) . "'";
        }
        return $key; // Integers are used as is.
    }

    /**
     * Extracts and flattens response headers.
     * @param PsrResponseInterface $response
     * @return array<string, string>
     */
    protected function getResponseHeaders(PsrResponseInterface $response): array
    {
        $flatHeaders = [];
        foreach ($response->getHeaders() as $name => $values) {
            $flatHeaders[$name] = implode(', ', $values);
        }
        return $flatHeaders;
    }

    /**
     * Placeholder for converting an array to an entity.
     * Useful when client methods accept array data.
     * Concrete clients should provide a proper implementation.
     * @param string $entitySet The entity set name (can imply entity type).
     * @param array<string, mixed> $data
     * @param string|int|null $id
     * @return EntityInterface
     */
    protected function arrayToEntity(string $entitySet, array $data, string|int|null $id = null): EntityInterface
    {
        // Basic implementation, concrete client might have more sophisticated type mapping
        $entity = new Entity($entitySet, $data, $id); // Using the basic Entity from Common
        // If 'id' or common ID properties are in $data, ensure they are set on the entity
        if ($id === null) {
            $idKey = $this->findIdKeyInData($data);
            if ($idKey !== null && isset($data[$idKey])) {
                $entity = new Entity($entitySet, $data, $data[$idKey]);
            }
        }
        return $entity;
    }

    /**
     * Tries to find a common ID key in data array.
     * @param array<string,mixed> $data
     * @return string|null
     */
    private function findIdKeyInData(array $data): ?string
    {
        $commonIdKeys = ['id', 'Id', 'ID']; // Extend as needed
        foreach($commonIdKeys as $key) {
            if (array_key_exists($key, $data)) {
                return $key;
            }
        }
        return null;
    }


    /**
     * Creates a bare entity, e.g., after a 204 response.
     * @param string $entitySet
     * @param string|int $id
     * @param string|null $eTag
     * @return EntityInterface
     */
    protected function createBareEntity(string $entitySet, string|int $id, ?string $eTag): EntityInterface
    {
        $entity = new Entity($entitySet, [], $id, $eTag, false); // Mark as not new
        return $entity;
    }

    /**
     * Extracts an ID from a Location header.
     * This is a simplistic approach and might need to be more robust.
     * Example Location: https://services.odata.org/V4/TripPinServiceRW/People('russellwhyte')
     * @param string $locationHeader
     * @param string $entitySetHint
     * @return string|int|null
     */
    protected function extractIdFromLocation(string $locationHeader, string $entitySetHint): string|int|null
    {
        // Example: People('russellwhyte') or People(123)
        if (preg_match('/' . preg_quote($entitySetHint, '/') . '\(([^)]+)\)/i', $locationHeader, $matches)) {
            $idSegment = $matches[1];
            // If ID is quoted string like 'russellwhyte', remove quotes
            if (str_starts_with($idSegment, "'") && str_ends_with($idSegment, "'")) {
                return trim($idSegment, "'");
            }
            // If ID is numeric
            if (is_numeric($idSegment)) {
                return (int)$idSegment;
            }
            return $idSegment; // Fallback
        }
        return null;
    }


    /**
     * Provides the specific Client version header for the client.
     * Must be implemented by concrete V2/V4 clients.
     * @return array<string, string> Example: ['Client-Version' => '4.0']
     */
    abstract protected function getODataVersionHeader(): array;

    /**
     * {@inheritDoc}
     * This should be implemented by concrete (V2/V4) clients to return their specific QueryBuilder.
     */
    abstract public function createQueryBuilder(?string $entitySet = null): QueryBuilderInterface;

    /**
     * {@inheritDoc}
     */
    abstract public function callFunction(
        string $functionName,
        array $parameters = [],
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null,
        ?Closure $queryConfigurator = null
    ): mixed;

    /**
     * {@inheritDoc}
     */
    abstract public function callAction(
        string $actionName,
        array $parameters = [],
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null
    ): mixed;

    /**
     * {@inheritDoc}
     */
    abstract public function executeBatch(array $requests): array;
}