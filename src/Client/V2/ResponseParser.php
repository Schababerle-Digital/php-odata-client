<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\V2;

use SchababerleDigital\OData\Client\Common\AbstractResponseParser;
use SchababerleDigital\OData\Client\Common\Entity; // Using common Entity for now
use SchababerleDigital\OData\Client\Common\EntityCollection; // Using common Collection
use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;
use SchababerleDigital\OData\Exception\ParseException;
use JsonException;

/**
 * Client V2 specific response parser.
 * Handles the V2 JSON format, often characterized by a "d" wrapper object.
 */
class ResponseParser extends AbstractResponseParser
{
    public const V2_WRAPPER_D = 'd';
    public const V2_RESULTS_PROPERTY = 'results';
    public const V2_COUNT_PROPERTY = '__count';
    public const V2_NEXT_LINK_PROPERTY = '__next';
    public const V2_METADATA_PROPERTY = '__metadata';

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding or V2 structure parsing fails.
     */
    public function parseCollection(string $responseBody, array $headers = []): EntityCollectionInterface
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ParseException('Failed to decode JSON for collection: ' . $e->getMessage(), $responseBody, 0, $e);
        }

        $dataWrapper = $decoded[self::V2_WRAPPER_D] ?? null;

        if (!is_array($dataWrapper)) {
            // Handle cases where response might be an array of entities directly (less common for V2 collections)
            if (is_array($decoded) && (isset($decoded[0]) || empty($decoded))) { // Check if it's a list of items or an empty array
                $dataWrapper = [self::V2_RESULTS_PROPERTY => $decoded]; // Treat as if it was in results
            } else {
                throw new ParseException('Client V2 response for collection is missing "d" wrapper or is not structured as expected.', $responseBody);
            }
        }

        $results = $dataWrapper[self::V2_RESULTS_PROPERTY] ?? null;
        if (!is_array($results)) {
            // If 'results' is not found, but 'd' itself is an array, it might be a collection of complex types or primitives
            if (is_array($dataWrapper) && (isset($dataWrapper[0]) || empty($dataWrapper))) {
                $results = $dataWrapper;
            } else {
                // If 'd' is an object without 'results', it might be a single entity passed to parseCollection mistakenly.
                // Or an empty collection represented differently. For an empty collection, 'results' could be an empty array.
                // If 'results' is not an array and not null, it's an error. If null, assume empty.
                if ($results !== null) {
                    throw new ParseException('Client V2 collection "results" property is not an array.', $responseBody);
                }
                $results = []; // Assume empty collection
            }
        }

        $entities = [];
        foreach ($results as $itemData) {
            if (is_array($itemData)) {
                $entities[] = $this->parseSingleEntityStructure($itemData);
            }
        }

        $collection = $this->createEntityCollectionInstance($entities);

        if (isset($dataWrapper[self::V2_COUNT_PROPERTY])) {
            $collection->setTotalCount((int)$dataWrapper[self::V2_COUNT_PROPERTY]);
        }
        if (isset($dataWrapper[self::V2_NEXT_LINK_PROPERTY])) {
            $collection->setNextLink((string)$dataWrapper[self::V2_NEXT_LINK_PROPERTY]);
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding or V2 structure parsing fails.
     */
    public function parseEntity(string $responseBody, array $headers = []): EntityInterface
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ParseException('Failed to decode JSON for entity: ' . $e->getMessage(), $responseBody, 0, $e);
        }

        $entityData = $decoded[self::V2_WRAPPER_D] ?? $decoded; // Some V2 services might not wrap single entities in 'd'.

        if (!is_array($entityData)) {
            throw new ParseException('Client V2 response for entity is not a valid object structure.', $responseBody);
        }

        // If $entityData was $decoded.d, and $decoded.d.results exists, it's likely a collection response
        if (isset($entityData[self::V2_RESULTS_PROPERTY]) && is_array($entityData[self::V2_RESULTS_PROPERTY])) {
            throw new ParseException('Client V2 response appears to be a collection, but an entity was expected.', $responseBody);
        }

        return $this->parseSingleEntityStructure($entityData);
    }

    /**
     * Parses the structure of a single Client V2 entity.
     * @param array<string, mixed> $entityData The array representing the entity data (potentially including __metadata).
     * @return EntityInterface
     * @throws ParseException If essential metadata for entity type is missing.
     */
    protected function parseSingleEntityStructure(array $entityData): EntityInterface
    {
        $metadata = $entityData[self::V2_METADATA_PROPERTY] ?? [];
        $uri = is_string($metadata['uri'] ?? null) ? $metadata['uri'] : null;
        $eTag = is_string($metadata['etag'] ?? null) ? $metadata['etag'] : null;
        $entityType = is_string($metadata['type'] ?? null) ? $this->extractEntityTypeFromV2Type($metadata['type']) : 'Unknown';

        if ($entityType === 'Unknown' && $uri) {
            // Attempt to infer entity type from URI if not in __metadata.type
            // Example URI: "http://services.odata.org/V2/Northwind/Northwind.svc/Customers('ALFKI')"
            $path = parse_url($uri, PHP_URL_PATH) ?: '';
            $segments = explode('/', trim($path, '/'));
            $lastSegment = end($segments);
            if ($lastSegment && !str_contains($lastSegment, '(')) { // If it's like .../EntityType
                $entityType = $lastSegment;
            } elseif (count($segments) > 1 && str_contains($lastSegment, '(')) { // If it's like .../EntitySet('key')
                $entityType = $segments[count($segments)-2]; // Get the segment before the key, assuming it's the EntityType/Set
            }
        }

        $properties = [];
        $id = null;

        foreach ($entityData as $key => $value) {
            if ($key === self::V2_METADATA_PROPERTY) {
                continue;
            }
            // In V2, deferred navigation properties are objects with a __deferred key.
            // For now, we'll treat them as simple properties if they are not expanded.
            // Expanded navigation properties would be arrays (collections) or objects (single entity).
            // A more sophisticated parser would handle this by creating nested Entity/EntityCollection instances.
            $properties[$key] = $value;
        }

        // Try to find a common ID property, V2 doesn't have a standard ID field in __metadata itself.
        // It's usually one of the properties like 'CustomerID', 'ProductID', etc.
        // For simplicity, we don't automatically determine the ID field here without more context.
        // The consuming code might need to know the ID property name.

        return $this->createEntityInstance($entityType, $properties, $id, $eTag);
    }

    /**
     * {@inheritDoc}
     */
    protected function createEntityInstance(
        string $entityType,
        array $data,
        string|int|null $id = null,
        ?string $eTag = null
    ): EntityInterface {
        // For V2, the ID might be part of the $data if the key property is known.
        // This basic implementation uses the common Entity.
        return new Entity($entityType, $data, $id, $eTag, $id === null && $eTag === null);
    }

    /**
     * {@inheritDoc}
     */
    protected function createEntityCollectionInstance(array $entities): EntityCollectionInterface
    {
        return new EntityCollection($entities);
    }

    /**
     * Extracts the simple entity type name from a V2 fully qualified type name.
     * e.g., "NorthwindModel.Customer" becomes "Customer".
     * @param string $v2TypeName
     * @return string
     */
    protected function extractEntityTypeFromV2Type(string $v2TypeName): string
    {
        $parts = explode('.', $v2TypeName);
        return end($parts) ?: $v2TypeName;
    }

    /**
     * {@inheritDoc}
     * Overrides to check for V2 specific locations of nextLink.
     * @throws ParseException If JSON decoding fails.
     */
    public function extractNextLink(string $responseBody, array $headers = []): ?string
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            $dataWrapper = $decoded[self::V2_WRAPPER_D] ?? null;
            if (is_array($dataWrapper) && isset($dataWrapper[self::V2_NEXT_LINK_PROPERTY])) {
                return (string)$dataWrapper[self::V2_NEXT_LINK_PROPERTY];
            }
            return null; // Fallback to default if V2 structure not found
        } catch (JsonException $e) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     * Overrides to check for V2 specific locations of inline count.
     * @throws ParseException If JSON decoding fails or count not found/invalid.
     */
    public function extractInlineCount(string $responseBody, array $headers = []): ?int
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            $dataWrapper = $decoded[self::V2_WRAPPER_D] ?? null;
            if (is_array($dataWrapper) && isset($dataWrapper[self::V2_COUNT_PROPERTY])) {
                return (int)$dataWrapper[self::V2_COUNT_PROPERTY];
            }
            return null;
        } catch (JsonException $e) {
            return null;
        }
    }
}