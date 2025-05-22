<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\V4;

use SchababerleDigital\OData\Client\Common\AbstractResponseParser;
use SchababerleDigital\OData\Client\Common\Entity; // Using common Entity for now
use SchababerleDigital\OData\Client\Common\EntityCollection; // Using common Collection
use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;
use SchababerleDigital\OData\Exception\ParseException;
use JsonException;

/**
 * Client V4 specific response parser.
 * Handles the V4 JSON format with "@odata" annotations.
 */
class ResponseParser extends AbstractResponseParser
{
    // Constants for V4 specific @odata annotations are already defined
    // in AbstractResponseParser (ODATA_COUNT_PROPERTY, ODATA_NEXT_LINK_PROPERTY, etc.)
    // and align with V4 conventions.

    public const ODATA_CONTEXT_PROPERTY = '@odata.context';
    public const ODATA_ETAG_PROPERTY = '@odata.etag';
    public const ODATA_ID_PROPERTY = '@odata.id';
    public const ODATA_TYPE_PROPERTY = '@odata.type';


    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding or V4 structure parsing fails.
     */
    public function parseCollection(string $responseBody, array $headers = []): EntityCollectionInterface
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ParseException('Failed to decode JSON for V4 collection: ' . $e->getMessage(), $responseBody, 0, $e);
        }

        if (!is_array($decoded) || !isset($decoded[self::ODATA_VALUE_PROPERTY]) || !is_array($decoded[self::ODATA_VALUE_PROPERTY])) {
            // Check if the decoded response itself is an array (e.g. collection of primitives/complex types not in 'value')
            if (is_array($decoded) && (isset($decoded[0]) || empty($decoded))) {
                $results = $decoded; // Treat the whole response as the array of items
            } else {
                throw new ParseException('Client V4 response for collection is missing "value" array or is not structured as expected.', $responseBody);
            }
        } else {
            $results = $decoded[self::ODATA_VALUE_PROPERTY];
        }


        $entities = [];
        foreach ($results as $itemData) {
            if (is_array($itemData)) {
                $entities[] = $this->parseSingleEntityStructure($itemData, $decoded[self::ODATA_CONTEXT_PROPERTY] ?? null);
            } else {
                // Handle collection of primitive types if needed
                // For now, assuming collection of entities (arrays)
            }
        }

        $collection = $this->createEntityCollectionInstance($entities);

        if (isset($decoded[self::ODATA_COUNT_PROPERTY])) {
            $collection->setTotalCount((int)$decoded[self::ODATA_COUNT_PROPERTY]);
        }
        if (isset($decoded[self::ODATA_NEXT_LINK_PROPERTY])) {
            $collection->setNextLink((string)$decoded[self::ODATA_NEXT_LINK_PROPERTY]);
        }
        if (isset($decoded[self::ODATA_DELTA_LINK_PROPERTY])) {
            $collection->setDeltaLink((string)$decoded[self::ODATA_DELTA_LINK_PROPERTY]);
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     * @throws ParseException If JSON decoding or V4 structure parsing fails.
     */
    public function parseEntity(string $responseBody, array $headers = []): EntityInterface
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ParseException('Failed to decode JSON for V4 entity: ' . $e->getMessage(), $responseBody, 0, $e);
        }

        if (!is_array($decoded)) {
            throw new ParseException('Client V4 response for entity is not a valid object structure.', $responseBody);
        }
        // In V4, a single entity response is the entity object itself, not wrapped in "value".
        // It might contain a "value" property if the entity itself has a property named "value".
        if (isset($decoded[self::ODATA_VALUE_PROPERTY]) && is_array($decoded[self::ODATA_VALUE_PROPERTY]) && count($decoded) <= 3) {
            // This might be a collection response with a single item, or a property named 'value'.
            // If it only contains @odata.context, @odata.count, and value, it's likely a collection.
            // This check is heuristic. Proper distinction often relies on @odata.context.
        }


        return $this->parseSingleEntityStructure($decoded, $decoded[self::ODATA_CONTEXT_PROPERTY] ?? null);
    }

    /**
     * Parses the structure of a single Client V4 entity.
     * @param array<string, mixed> $entityData The array representing the entity data.
     * @param string|null $contextUri The @odata.context URI from the response, can help determine entity type.
     * @return EntityInterface
     */
    protected function parseSingleEntityStructure(array $entityData, ?string $contextUri = null): EntityInterface
    {
        $eTag = $entityData[self::ODATA_ETAG_PROPERTY] ?? null;
        $odataId = $entityData[self::ODATA_ID_PROPERTY] ?? null; // Full Client ID
        $odataType = $entityData[self::ODATA_TYPE_PROPERTY] ?? null;

        $id = null;
        if ($odataId) {
            // Attempt to extract the key from the full @odata.id
            // Example: "Customers('ALFKI')" or "Products(1)"
            if (preg_match('/\((\'?([^\']+)\'?)\)$/', $odataId, $matches)) {
                $id = is_numeric($matches[2]) ? (int)$matches[2] : $matches[2];
            }
        }

        $entityType = $odataType ? $this->extractEntityTypeFromV4Type($odataType) : 'Unknown';

        if ($entityType === 'Unknown' && $contextUri) {
            // Try to infer from @odata.context. e.g., "...$metadata#Customers/$entity"
            if (preg_match('/\$metadata#([a-zA-Z0-9_]+)(?:\([^\)]*\))?\/@Element$/i', $contextUri, $matches) ||
                preg_match('/\$metadata#([a-zA-Z0-9_]+)(?:\([^\)]*\))?\/ \$entity$/i', $contextUri, $matches) || // some services add space
                preg_match('/\$metadata#([a-zA-Z0-9_]+)\/\$entity$/i', $contextUri, $matches) ||
                preg_match('/\$metadata#([a-zA-Z0-9_]+)$/i', $contextUri, $matches)) { // Context for a single entity by key
                $entityType = $matches[1];
            }
        }

        $properties = [];
        $navigationPropertiesToParse = [];

        foreach ($entityData as $key => $value) {
            if (str_starts_with($key, '@odata.')) {
                // Client annotations are handled separately (etag, id, type, context etc.)
                // Navigation links (e.g. "Prop@odata.navigationLinkUrl") are also annotations.
                // Expanded navigation properties (e.g. "Prop": {...} or "Prop": [{...}]) are not annotations.
                continue;
            }

            // Check for expanded navigation properties
            // Single navigation property: value is an object (array in PHP)
            // Collection navigation property: value is an array of objects
            if (is_array($value)) {
                // Heuristic: if it looks like an entity or collection of entities, treat as nav prop
                if (isset($value[0]) && is_array($value[0])) { // Likely a collection
                    $navigationPropertiesToParse[$key] = ['type' => 'collection', 'data' => $value];
                } elseif (!empty($value) && array_keys($value) !== range(0, count($value) -1)) { // Likely a single entity (associative array)
                    $navigationPropertiesToParse[$key] = ['type' => 'entity', 'data' => $value];
                } else {
                    $properties[$key] = $value; // Regular array property
                }
            } else {
                $properties[$key] = $value; // Primitive property
            }
        }

        $entity = $this->createEntityInstance($entityType, $properties, $id, is_string($eTag) ? $eTag : null);

        // Parse and set navigation properties
        foreach ($navigationPropertiesToParse as $navKey => $navInfo) {
            if ($navInfo['type'] === 'entity') {
                $entity->setNavigationProperty($navKey, $this->parseSingleEntityStructure($navInfo['data']));
            } elseif ($navInfo['type'] === 'collection') {
                $navEntities = [];
                foreach ($navInfo['data'] as $navItemData) {
                    if (is_array($navItemData)) {
                        $navEntities[] = $this->parseSingleEntityStructure($navItemData);
                    }
                }
                $entity->setNavigationProperty($navKey, $this->createEntityCollectionInstance($navEntities));
            }
        }
        return $entity;
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
        // This basic implementation uses the common Entity.
        // A more advanced setup might use a factory to create specific entity classes based on $entityType.
        $isNew = true;
        // An entity from server response is never "new" in client context, it has an ID/ETag or representation.
        if ($id !== null || $eTag !== null || !empty($data) || $entityType !== 'Unknown') {
            $isNew = false;
        }
        return new Entity($entityType, $data, $id, $eTag, $isNew);
    }

    /**
     * {@inheritDoc}
     */
    protected function createEntityCollectionInstance(array $entities): EntityCollectionInterface
    {
        return new EntityCollection($entities);
    }

    /**
     * Extracts the simple entity type name from a V4 @odata.type string.
     * e.g., "#Microsoft.Client.SampleService.Models.TripPin.Person" becomes "Person".
     * @param string $odataType The value of @odata.type.
     * @return string The simple type name.
     */
    protected function extractEntityTypeFromV4Type(string $odataType): string
    {
        if (str_starts_with($odataType, '#')) {
            $odataType = substr($odataType, 1);
        }
        $parts = explode('.', $odataType);
        return end($parts) ?: $odataType;
    }
}