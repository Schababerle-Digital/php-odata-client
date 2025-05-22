<?php

declare(strict_types=1);

namespace SchababerleDigital\OData;

use SchababerleDigital\OData\Exception\ParseException;
use InvalidArgumentException;

/**
 * Represents a parsed Client Service Document.
 * Provides access to listed entity sets, singletons, function imports, and action imports.
 */
class ServiceDocument
{
    protected ?string $contextUrl = null;
    /** @var array<int, array{name: string, kind: string, url: string}> */
    protected array $entitySets = [];
    /** @var array<int, array{name: string, kind: string, url: string}> */
    protected array $singletons = [];
    /** @var array<int, array{name: string, kind: string, url: string, title?: string}> */
    protected array $functionImports = [];
    /** @var array<int, array{name: string, kind: string, url: string, title?: string}> */
    protected array $actionImports = [];
    /** @var array<string, mixed> */
    protected array $rawServiceDocument;
    protected int $odataVersion;

    protected const V4_CONTEXT = '@odata.context';
    protected const V4_VALUE = 'value';
    protected const V2_WRAPPER_D = 'd';
    protected const V2_ENTITY_SETS = 'EntitySets';

    /**
     * @param array<string, mixed> $decodedServiceDocument The already JSON-decoded service document content.
     * @param int $odataVersion The Client protocol version (2 or 4) this document pertains to.
     * @throws ParseException If the service document structure is invalid for the specified version.
     * @throws InvalidArgumentException If an unsupported Client version is provided.
     */
    public function __construct(array $decodedServiceDocument, int $odataVersion)
    {
        $this->rawServiceDocument = $decodedServiceDocument;
        $this->odataVersion = $odataVersion;

        if ($this->odataVersion === 4) {
            $this->parseV4($decodedServiceDocument);
        } elseif ($this->odataVersion === 2) {
            $this->parseV2($decodedServiceDocument);
        } else {
            throw new InvalidArgumentException("Unsupported Client version provided: {$this->odataVersion}. Only 2 or 4 are supported.");
        }
    }

    /**
     * Parses a V4 Client service document.
     * @param array<string, mixed> $document
     * @throws ParseException
     */
    protected function parseV4(array $document): void
    {
        $this->contextUrl = isset($document[self::V4_CONTEXT]) && is_string($document[self::V4_CONTEXT])
            ? $document[self::V4_CONTEXT]
            : null;

        $values = $document[self::V4_VALUE] ?? null;
        if (!is_array($values)) {
            throw new ParseException('Client V4 service document is missing "value" array or it is not an array.');
        }

        foreach ($values as $item) {
            if (!is_array($item) || !isset($item['name'], $item['kind'], $item['url'])) {
                // Skip malformed items or log a warning
                continue;
            }
            $entry = [
                'name' => (string)$item['name'],
                'kind' => (string)$item['kind'],
                'url' => (string)$item['url'],
                'title' => isset($item['title']) && is_string($item['title']) ? $item['title'] : null,
            ];

            match ($entry['kind']) {
                'EntitySet' => $this->entitySets[] = $entry,
                'Singleton' => $this->singletons[] = $entry,
                'FunctionImport' => $this->functionImports[] = $entry,
                'ActionImport' => $this->actionImports[] = $entry,
                default => null, // Unknown kind
            };
        }
    }

    /**
     * Parses a V2 Client service document (JSON format).
     * @param array<string, mixed> $document
     * @throws ParseException
     */
    protected function parseV2(array $document): void
    {
        $dWrapper = $document[self::V2_WRAPPER_D] ?? null;
        if (!is_array($dWrapper)) {
            throw new ParseException('Client V2 service document (JSON) is missing "d" wrapper or it is not an object.');
        }

        $entitySetNames = $dWrapper[self::V2_ENTITY_SETS] ?? null;
        if (!is_array($entitySetNames)) {
            throw new ParseException('Client V2 service document (JSON) "d.EntitySets" is missing or not an array.');
        }

        foreach ($entitySetNames as $name) {
            if (!is_string($name)) {
                // Skip malformed items
                continue;
            }
            $this->entitySets[] = [
                'name' => $name,
                'kind' => 'EntitySet', // V2 JSON format usually just lists names for EntitySets
                'url' => $name, // URL is typically the name itself relative to service root
            ];
        }
        // V2 JSON service documents are typically less rich than V4 regarding singletons, function/action imports.
        // These are usually discovered via $metadata in V2.
    }

    /**
     * Gets the list of EntitySets.
     * Each entry is an array: ['name' => string, 'kind' => 'EntitySet', 'url' => string, 'title' => ?string (V4 only)]
     * @return array<int, array{name: string, kind: string, url: string, title?: string|null}>
     */
    public function getEntitySets(): array
    {
        return $this->entitySets;
    }

    /**
     * Gets the list of Singletons (primarily Client V4).
     * Each entry is an array: ['name' => string, 'kind' => 'Singleton', 'url' => string, 'title' => ?string]
     * @return array<int, array{name: string, kind: string, url: string, title?: string|null}>
     */
    public function getSingletons(): array
    {
        return $this->singletons;
    }

    /**
     * Gets the list of FunctionImports (primarily Client V4).
     * Each entry is an array: ['name' => string, 'kind' => 'FunctionImport', 'url' => string, 'title' => ?string]
     * @return array<int, array{name: string, kind: string, url: string, title?: string|null}>
     */
    public function getFunctionImports(): array
    {
        return $this->functionImports;
    }

    /**
     * Gets the list of ActionImports (primarily Client V4).
     * Each entry is an array: ['name' => string, 'kind' => 'ActionImport', 'url' => string, 'title' => ?string]
     * @return array<int, array{name: string, kind: string, url: string, title?: string|null}>
     */
    public function getActionImports(): array
    {
        return $this->actionImports;
    }

    /**
     * Gets the @odata.context URL from the V4 service document, if available.
     * @return string|null
     */
    public function getContextUrl(): ?string
    {
        return $this->contextUrl;
    }

    /**
     * Gets the raw, decoded service document array that was used to construct this object.
     * @return array<string, mixed>
     */
    public function getRawServiceDocument(): array
    {
        return $this->rawServiceDocument;
    }

    /**
     * Gets the Client version this service document was parsed for.
     * @return int
     */
    public function getODataVersion(): int
    {
        return $this->odataVersion;
    }
}