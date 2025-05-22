<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\V4;

use Closure;
use SchababerleDigital\OData\Client\Common\AbstractClient;
use SchababerleDigital\OData\Client\Common\AbstractResponseParser;
use SchababerleDigital\OData\Contract\HttpClientInterface;
use SchababerleDigital\OData\Contract\QueryBuilderInterface;
use SchababerleDigital\OData\Contract\ResponseParserInterface;
use SchababerleDigital\OData\Contract\SerializerInterface;
use SchababerleDigital\OData\Exception\ODataRequestException;
use JsonException;


/**
 * Client V4 specific client.
 */
class Client extends AbstractClient
{
    private const ODATA_V4_VERSION_HEADER_VALUE = '4.1';

    /**
     * @param HttpClientInterface $httpClient
     * @param ResponseParserInterface $responseParser Typically an instance of SchababerleDigital\Client\V4\ResponseParser
     * @param SerializerInterface $serializer
     * @param string $baseServiceUrl
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ResponseParserInterface $responseParser, // Should be V4Parser
        SerializerInterface $serializer, // Consider if a V4Serializer is needed
        string $baseServiceUrl
    ) {
        parent::__construct($httpClient, $responseParser, $serializer, $baseServiceUrl);
    }

    /**
     * {@inheritDoc}
     */
    protected function getODataVersionHeader(): array
    {
        // AbstractClient::DEFAULT_HEADERS already sets Client-Version: 4.0
        // This method confirms or could specify a more precise version like 4.01 if needed.
        return [
            'Client-Version' => self::ODATA_V4_VERSION_HEADER_VALUE,
            // Client-MaxVersion can also be sent by client if it supports a range.
        ];
    }

    /**
     * {@inheritDoc}
     * Returns a V4 specific QueryBuilder.
     */
    public function createQueryBuilder(?string $entitySet = null): QueryBuilderInterface
    {
        $builder = new QueryBuilder(); // V4 QueryBuilder
        if ($entitySet) {
            $builder->setEntitySet($entitySet);
        }
        return $builder;
    }

    /**
     * {@inheritDoc}
     * Client V4 Functions.
     */
    public function callFunction(
        string $functionName,
        array $parameters = [], // In V4, parameters for functions are typically in URL path or query string
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null,
        ?Closure $queryConfigurator = null
    ): mixed {
        $path = '';
        $isBound = false;

        if ($bindingEntitySet) {
            $isBound = true;
            $path .= $bindingEntitySet;
            if ($bindingEntityId !== null) {
                $path .= '(' . $this->encodeKey($bindingEntityId) . ')';
            }
            // In V4, a bound function is part of the namespace of the binding type.
            // e.g. /Users('id')/Namespace.FunctionName(param=@val)
            // For simplicity, we assume functionName might already include namespace if needed by service.
            // Or that the service figures it out without full namespace in path if it's unique.
            // A more robust way is to get function metadata and use its FQN.
            $path .= '/' . $functionName; // Or My.Namespace.FunctionName
        } else {
            $path .= $functionName; // Unbound function, typically a function import
        }

        // V4 Function parameters syntax: FunctionName(Param1=@Param1,Param2=@Param2)
        // Or FunctionName?Param1=@Param1&Param2=@Param2 for unbound in query string
        $parameterSegments = [];
        if (!empty($parameters)) {
            if ($isBound || str_contains($path, '(')) { // If already has key or function name is complex
                // Append to query string for bound functions or if parameters are not inline
                foreach ($parameters as $key => $value) {
                    // Parameters in query string are usually direct key=value
                    // Alias syntax (@p) is for values in the path segment (Parentheses part of URL)
                    // Let QueryBuilder handle correct encoding
                    $parameters[$key] = $this->formatFunctionParameterValue($value);
                }
            } else { // Attempt inline parameters for unbound functions
                foreach ($parameters as $key => $value) {
                    $parameterSegments[] = $key . '=' . $this->formatFunctionParameterValue($value);
                }
                if (!empty($parameterSegments)) {
                    $path .= '(' . implode(',', $parameterSegments) . ')';
                }
                $parameters = []; // Parameters are now in path
            }
        }

        $queryBuilder = $this->createQueryBuilder(null);
        // Add any remaining parameters (those not in path) to query options.
        // Or add function parameters that are meant to be query options.
        foreach($parameters as $key => $value) {
            $queryBuilder->custom($key, (string) $value);
        }

        if ($queryConfigurator !== null) {
            $queryConfigurator($queryBuilder);
        }

        $url = $this->buildUrl($path, $queryBuilder->getQueryParams());
        $response = $this->executeRequest('GET', $url); // Functions are GET
        $responseBody = (string)$response->getBody();
        $headers = $this->getResponseHeaders($response);

        try {
            // Response parsing logic (similar to V2 client, but using V4 parser)
            // Check Content-Type or @odata.context to determine if it's entity, collection, or primitive/complex
            $odataContext = $headers['Client-EntityId'] ?? ($decodedOdataContext = $this->extractOdataContext($responseBody)) ?? null;

            if (str_contains($odataContext ?? '', '$entity') || (isset($decodedOdataContext) && !isset(json_decode($responseBody, true)[AbstractResponseParser::ODATA_VALUE_PROPERTY])) ) {
                return $this->responseParser->parseEntity($responseBody, $headers);
            } elseif (isset(json_decode($responseBody, true)[AbstractResponseParser::ODATA_VALUE_PROPERTY])) {
                return $this->responseParser->parseCollection($responseBody, $headers);
            }
            return $this->responseParser->parseValue($responseBody, $headers);
        } catch (JsonException | \SchababerleDigital\OData\Exception\ParseException $e) {
            throw new ODataRequestException("Failed to parse V4 function response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * {@inheritDoc}
     * Client V4 Actions.
     */
    public function callAction(
        string $actionName,
        array $parameters = [], // Parameters are sent in the JSON body for POST
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null
    ): mixed {
        $path = '';
        if ($bindingEntitySet) {
            $path .= $bindingEntitySet;
            if ($bindingEntityId !== null) {
                $path .= '(' . $this->encodeKey($bindingEntityId) . ')';
            }
            // Similar to functions, V4 bound actions are part of namespace
            $path .= '/' . $actionName; // Or My.Namespace.ActionName
        } else {
            $path .= $actionName; // Unbound action (action import)
        }

        $url = $this->buildUrl($path);
        $body = empty($parameters) ? null : json_encode($parameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [];
        if ($body !== null) {
            // V4 specifies application/json for action parameters.
            // Client-Version and Accept are handled by executeRequest.
            $headers['Content-Type'] = self::JSON_CONTENT_TYPE . ';charset=utf-8';
        }

        $response = $this->executeRequest('POST', $url, $headers, $body);
        $responseBody = (string)$response->getBody();
        $statusCode = $response->getStatusCode();
        $headers = $this->getResponseHeaders($response);

        if ($statusCode === 204 || empty(trim($responseBody))) { // No content
            return true; // Or null, or some success indicator based on void return
        }

        try {
            // Similar parsing logic as callFunction
            $odataContext = $headers['Client-EntityId'] ?? ($decodedOdataContext = $this->extractOdataContext($responseBody)) ?? null;

            if (str_contains($odataContext ?? '', '$entity') || (isset($decodedOdataContext) && !isset(json_decode($responseBody, true)[AbstractResponseParser::ODATA_VALUE_PROPERTY])) ) {
                return $this->responseParser->parseEntity($responseBody, $headers);
            } elseif (isset(json_decode($responseBody, true)[AbstractResponseParser::ODATA_VALUE_PROPERTY])) {
                return $this->responseParser->parseCollection($responseBody, $headers);
            }
            return $this->responseParser->parseValue($responseBody, $headers);
        } catch (JsonException | \SchababerleDigital\OData\Exception\ParseException $e) {
            throw new ODataRequestException("Failed to parse V4 action response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }
    }

    /**
     * Formats a parameter value for an Client function call URL.
     * Strings are quoted, booleans become true/false, nulls become null.
     * @param mixed $value
     * @return string
     */
    private function formatFunctionParameterValue(mixed $value): string
    {
        if (is_string($value)) {
            // Check if it's an Client enum: Namespace.EnumType'EnumValue'
            if (preg_match('/^[a-zA-Z0-9_.]+\'[a-zA-Z0-9_]+\'$/', $value)) {
                return $value;
            }
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        // For arrays or objects (e.g. for complex types or collections as parameters)
        // Client V4 supports JSON encoding for these.
        if (is_array($value)) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch(JsonException $e) {
                // Fallback or throw error
                return 'null';
            }
        }
        return (string)$value;
    }

    /**
     * Helper to extract @odata.context from response body if present.
     * @param string $responseBody
     * @return string|null
     */
    private function extractOdataContext(string $responseBody): ?string
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? ($decoded[ResponseParser::ODATA_CONTEXT_PROPERTY] ?? null) : null;
        } catch (JsonException) {
            return null;
        }
    }


    /**
     * {@inheritDoc}
     * Client V4 batch requests use a specific JSON format or multipart/mixed format.
     * The JSON format for batch is simpler to implement first.
     * @throws ODataRequestException If batch request fails.
     */
    public function executeBatch(array $requests): array
    {
        // V4 JSON Batch Format:
        // { "requests": [ { "id": "1", "method": "GET", "url": "/Products", "headers": {"Client-Version":"4.0"} }, ... ] }
        // This example implements the JSON batch format. Multipart is more complex.

        $batchRequestBody = ['requests' => []];
        foreach ($requests as $index => $requestItem) {
            if (!isset($requestItem['method'], $requestItem['url'])) {
                throw new InvalidArgumentException("Each batch request must have 'method' and 'url'.");
            }
            $batchRequest = [
                'id' => $requestItem['id'] ?? (string)($index + 1),
                'method' => strtoupper($requestItem['method']),
                'url' => ltrim($requestItem['url'], '/'), // URLs in batch are relative to service root
                'headers' => array_merge(
                    $this->getODataVersionHeader(), // Ensure Client version is in each part
                    ['Accept' => 'application/json;odata.metadata=minimal'], // Consistent accept
                    $requestItem['headers'] ?? []
                ),
            ];
            if (isset($requestItem['body'])) {
                $batchRequest['body'] = $requestItem['body']; // Body should already be prepared (e.g., JSON string or object for Guzzle to handle)
                // Ensure Content-Type is set if body is present and not already in headers
                if (!isset($batchRequest['headers']['Content-Type']) && !isset($batchRequest['headers']['content-type'])) {
                    $batchRequest['headers']['Content-Type'] = self::JSON_CONTENT_TYPE . ';charset=utf-8';
                }
            }
            $batchRequestBody['requests'][] = $batchRequest;
        }

        $url = $this->baseServiceUrl . '$batch';
        $body = json_encode($batchRequestBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // For JSON batch, Content-Type is application/json
        $response = $this->executeRequest(
            'POST',
            $url,
            ['Content-Type' => self::JSON_CONTENT_TYPE . ';charset=utf-8', 'Accept' => self::JSON_CONTENT_TYPE],
            $body
        );

        $responseBody = (string)$response->getBody();
        try {
            $decodedResponse = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ODataRequestException("Failed to parse $batch response: " . $e->getMessage(), 0, $e, ['url' => $url]);
        }

        if (!isset($decodedResponse['responses']) || !is_array($decodedResponse['responses'])) {
            throw new ODataRequestException("Invalid batch response format: 'responses' array missing.", 0, null, ['url' => $url, 'response_body' => $responseBody]);
        }

        // Process individual responses within the batch
        // For now, just returning the raw array of responses from batch.
        // A more sophisticated client would parse each body into Entity/Collection or error.
        return $decodedResponse['responses'];
    }
}