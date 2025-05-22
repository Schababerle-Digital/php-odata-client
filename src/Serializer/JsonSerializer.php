<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Serializer;

use SchababerleDigital\OData\Contract\EntityInterface;
use SchababerleDigital\OData\Contract\SerializerInterface;
use SchababerleDigital\OData\Exception\SerializationException;
use JsonException;

/**
 * Serializes Client entities and batch requests to JSON.
 */
class JsonSerializer implements SerializerInterface
{
    protected string $contentType;
    protected int $jsonEncodeFlags;

    /**
     * @param string $contentType The Content-Type header value this serializer produces.
     * Default: "application/json;charset=utf-8".
     * @param int $jsonEncodeFlags Flags for json_encode function (e.g., JSON_UNESCAPED_SLASHES).
     */
    public function __construct(
        string $contentType = 'application/json;charset=utf-8',
        int $jsonEncodeFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    ) {
        $this->contentType = $contentType;
        $this->jsonEncodeFlags = $jsonEncodeFlags;
    }

    /**
     * {@inheritDoc}
     * This implementation serializes the entity's properties obtained via getProperties().
     * For linking to existing entities (navigation properties), include a property
     * like "NavigationProperty@odata.bind": "TargetCollection('key')" in the entity's properties.
     * @throws SerializationException If JSON encoding fails.
     */
    public function serializeEntity(EntityInterface $entity): string
    {
        $dataToSerialize = $entity->getProperties();

        // Client V4 allows @odata.type for specifying derived types in requests,
        // though often it's inferred by the endpoint.
        // If the entity has type information and it's different from a base type
        // expected by the endpoint, it could be added here.
        // Example: $dataToSerialize['@odata.type'] = '#Namespace.DerivedType';

        try {
            $json = json_encode($dataToSerialize, $this->jsonEncodeFlags | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializationException(
                'Failed to JSON encode entity: ' . $e->getMessage(),
                $dataToSerialize,
                0,
                $e
            );
        }
        return $json;
    }

    /**
     * {@inheritDoc}
     * Serializes an array of request definitions for an Client $batch request (V4 JSON format).
     * Each item in the $requests array should conform to the structure expected by Client batch requests,
     * typically including 'id', 'method', 'url', 'headers' (optional), and 'body' (optional).
     * Example structure for $requests argument:
     * [
     * [
     * 'id' => 'req-1',
     * 'method' => 'GET',
     * 'url' => 'Products(1)',
     * 'headers' => ['If-Match' => 'W/"xyz"']
     * ],
     * [
     * 'id' => 'req-2',
     * 'method' => 'POST',
     * 'url' => 'Products',
     * 'headers' => ['Content-Type' => 'application/json'],
     * 'body' => ['Name' => 'New Product', 'Price' => 100] // Body can be an array/object to be JSON encoded
     * ]
     * ]
     * The 'body' within each request part will also be JSON encoded if it's an array or object.
     * @throws SerializationException If JSON encoding of the batch request or any of its parts fails.
     */
    public function serializeBatch(array $requests): string
    {
        $batchPayload = ['requests' => []];

        foreach ($requests as $requestItem) {
            if (!isset($requestItem['method'], $requestItem['url'])) {
                throw new SerializationException("Each batch request item must have 'method' and 'url' keys.", $requestItem);
            }

            $individualRequest = [
                'id' => $requestItem['id'] ?? (string)(count($batchPayload['requests']) + 1),
                'method' => strtoupper($requestItem['method']),
                'url' => $requestItem['url'], // URL should be relative to the service root
            ];

            if (isset($requestItem['atomicityGroup'])) { // Client V4 specific
                $individualRequest['atomicityGroup'] = $requestItem['atomicityGroup'];
            }

            if (!empty($requestItem['headers']) && is_array($requestItem['headers'])) {
                $individualRequest['headers'] = $requestItem['headers'];
            }

            if (array_key_exists('body', $requestItem)) {
                // If body is an array or object, it should be JSON encoded.
                // If it's already a string, it's assumed to be pre-formatted (e.g. for other content types within batch).
                // For Client JSON batch, individual request bodies are usually also JSON.
                if (is_array($requestItem['body']) || is_object($requestItem['body'])) {
                    try {
                        // Individual request bodies are NOT re-encoded to JSON strings within the main JSON batch payload by Guzzle.
                        // They are expected to be PHP arrays/objects that Guzzle's `json` option will encode,
                        // OR they must be pre-encoded strings if using Guzzle's `body` option.
                        // For the Client JSON batch format, the 'body' field should contain the *object* that will form the JSON body,
                        // not a stringified JSON. The outer json_encode will handle it.
                        $individualRequest['body'] = $requestItem['body'];

                        // Ensure Content-Type for the individual request if body is present and not set
                        if (!isset($individualRequest['headers']['Content-Type']) && !isset($individualRequest['headers']['content-type'])) {
                            $individualRequest['headers']['Content-Type'] = 'application/json;charset=utf-8';
                        }

                    } catch (JsonException $e) {
                        throw new SerializationException(
                            'Failed to JSON encode body for batch request item ID "' . $individualRequest['id'] . '": ' . $e->getMessage(),
                            $requestItem['body'],
                            0,
                            $e
                        );
                    }
                } else {
                    $individualRequest['body'] = $requestItem['body']; // Assumed pre-formatted string or null
                }
            }
            $batchPayload['requests'][] = $individualRequest;
        }

        try {
            return json_encode($batchPayload, $this->jsonEncodeFlags | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializationException('Failed to JSON encode batch request: ' . $e->getMessage(), $batchPayload, 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }
}