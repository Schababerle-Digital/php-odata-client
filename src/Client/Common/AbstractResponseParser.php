<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\Common;

use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;
use SchababerleDigital\OData\Contract\ResponseParserInterface;
use SchababerleDigital\OData\Exception\ParseException;
use JsonException;

/**
 * Provides common functionality for Client response parsers.
 * Specific Client versions (V2, V4) will extend this class.
 */
abstract class AbstractResponseParser implements ResponseParserInterface
{
    public const ODATA_COUNT_PROPERTY = '@odata.count';
    public const ODATA_NEXT_LINK_PROPERTY = '@odata.nextLink';
    public const ODATA_DELTA_LINK_PROPERTY = '@odata.deltaLink';
    public const ODATA_VALUE_PROPERTY = 'value';
    public const ODATA_ERROR_PROPERTY = 'error';

    /**
     * {@inheritDoc}
     */
    abstract public function parseCollection(string $responseBody, array $headers = []): EntityCollectionInterface;

    /**
     * {@inheritDoc}
     */
    abstract public function parseEntity(string $responseBody, array $headers = []): EntityInterface;

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding fails.
     */
    public function parseValue(string $responseBody, array $headers = []): mixed
    {
        if (trim($responseBody) === '') {
            return null;
        }
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            // For a single value, Client might wrap it in a "value" property, or it might be the direct value.
            // This depends on the Client version and specific service implementation.
            // A common case is when $value is requested on a primitive property.
            if (is_array($decoded) && array_key_exists(static::ODATA_VALUE_PROPERTY, $decoded)) {
                return $decoded[static::ODATA_VALUE_PROPERTY];
            }
            return $decoded; // Or could be just the plain value if not wrapped
        } catch (JsonException $e) {
            throw new ParseException('Failed to decode JSON value: ' . $e->getMessage(), $responseBody, 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     * @throws ParseException If parsing fails.
     */
    public function parseCount(string $responseBody, array $headers = []): int
    {
        $trimmedBody = trim($responseBody);
        if (is_numeric($trimmedBody)) {
            return (int)$trimmedBody;
        }
        // Try to parse as JSON if it's not a plain number (e.g. Client V4 $count=true in a collection)
        try {
            $decoded = json_decode($trimmedBody, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded[static::ODATA_COUNT_PROPERTY])) {
                return (int)$decoded[static::ODATA_COUNT_PROPERTY];
            }
            if (is_int($decoded)){ // Case where response is just a number in JSON `123`
                return $decoded;
            }
        } catch (JsonException $e) {
            // Ignore if not JSON, it might be a plain text number handled above
        }
        throw new ParseException('Failed to parse count from response.', $responseBody);
    }

    /**
     * {@inheritDoc}
     */
    public function parseError(string $responseBody, int $statusCode, array $headers = []): array
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded[static::ODATA_ERROR_PROPERTY]) && is_array($decoded[static::ODATA_ERROR_PROPERTY])) {
                return [
                    'code' => $decoded[static::ODATA_ERROR_PROPERTY]['code'] ?? (string)$statusCode,
                    'message' => $decoded[static::ODATA_ERROR_PROPERTY]['message'] ?? 'Unknown Client error.',
                    'details' => $decoded[static::ODATA_ERROR_PROPERTY]['details'] ?? ($decoded[static::ODATA_ERROR_PROPERTY]['innererror'] ?? []),
                    'raw' => $decoded,
                ];
            }
            // If not a standard Client JSON error, return a generic structure
            return [
                'code' => (string)$statusCode,
                'message' => 'An HTTP error occurred.',
                'details' => ['raw_body' => $responseBody],
                'raw' => $responseBody, // Or null if not parsable as array
            ];
        } catch (JsonException $e) {
            return [
                'code' => (string)$statusCode,
                'message' => 'Failed to parse error response.',
                'details' => ['raw_body' => $responseBody, 'parse_error' => $e->getMessage()],
                'raw' => $responseBody,
            ];
        }
    }

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding fails.
     */
    public function extractNextLink(string $responseBody, array $headers = []): ?string
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) && isset($decoded[static::ODATA_NEXT_LINK_PROPERTY]) ? (string)$decoded[static::ODATA_NEXT_LINK_PROPERTY] : null;
        } catch (JsonException $e) {
            // If the body is not JSON or malformed, there's no nextLink in it.
            return null;
        }
    }

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding fails.
     */
    public function extractDeltaLink(string $responseBody, array $headers = []): ?string
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) && isset($decoded[static::ODATA_DELTA_LINK_PROPERTY]) ? (string)$decoded[static::ODATA_DELTA_LINK_PROPERTY] : null;
        } catch (JsonException $e) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding fails.
     */
    public function extractInlineCount(string $responseBody, array $headers = []): ?int
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) && isset($decoded[static::ODATA_COUNT_PROPERTY]) ? (int)$decoded[static::ODATA_COUNT_PROPERTY] : null;
        } catch (JsonException $e) {
            return null;
        }
    }

    /**
     * Helper method to create an entity instance.
     * Concrete parsers should implement this to map data to their specific Entity implementation.
     *
     * @param string $entityType The type of the entity.
     * @param array<string, mixed> $data The properties of the entity.
     * @param string|int|null $id The ID of the entity.
     * @param string|null $eTag The ETag of the entity.
     * @return EntityInterface
     */
    abstract protected function createEntityInstance(
        string $entityType,
        array $data,
        string|int|null $id = null,
        ?string $eTag = null
    ): EntityInterface;

    /**
     * Helper method to create an entity collection instance.
     * Concrete parsers should implement this.
     *
     * @param array<EntityInterface> $entities
     * @return EntityCollectionInterface
     */
    abstract protected function createEntityCollectionInstance(array $entities): EntityCollectionInterface;
}