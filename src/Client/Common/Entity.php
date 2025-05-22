<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\Common;

use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;

/**
 * A basic implementation of an Client entity.
 */
class Entity implements EntityInterface
{
    protected string|int|null $id = null;
    protected string $entityType;
    /** @var array<string, mixed> */
    protected array $properties = [];
    /** @var array<string, EntityInterface|EntityCollectionInterface|null> */
    protected array $navigationProperties = [];
    protected ?string $eTag = null;
    protected bool $isNewFlag = true;

    /**
     * @param string $entityType The Client entity type name.
     * @param array<string, mixed> $properties Initial data properties of the entity.
     * @param string|int|null $id The ID of the entity. If null, it might be considered new.
     * @param string|null $eTag The ETag of the entity.
     * @param bool $isNew Explicitly sets if the entity is new. Defaults to true if ID is null.
     */
    public function __construct(
        string $entityType,
        array $properties = [],
        string|int|null $id = null,
        ?string $eTag = null,
        ?bool $isNew = null
    ) {
        $this->entityType = $entityType;
        $this->properties = $properties;
        $this->id = $id;
        $this->eTag = $eTag;
        $this->isNewFlag = $isNew ?? ($id === null);
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string|int|null
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * {@inheritDoc}
     */
    public function getProperty(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function setProperty(string $name, mixed $value): self
    {
        $this->properties[$name] = $value;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * {@inheritDoc}
     */
    public function getNavigationProperty(string $name): EntityInterface|EntityCollectionInterface|null
    {
        return $this->navigationProperties[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function setNavigationProperty(string $name, EntityInterface|EntityCollectionInterface|null $value): self
    {
        $this->navigationProperties[$name] = $value;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getNavigationProperties(): array
    {
        return $this->navigationProperties;
    }

    /**
     * {@inheritDoc}
     */
    public function getETag(): ?string
    {
        return $this->eTag;
    }

    /**
     * {@inheritDoc}
     */
    public function setETag(?string $eTag): self
    {
        $this->eTag = $eTag;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return $this->getProperties();
    }

    /**
     * {@inheritDoc}
     */
    public function isNew(): bool
    {
        return $this->isNewFlag;
    }

    /**
     * Marks this entity instance as persisted (not new).
     * This is typically called after a successful create operation.
     *
     * @param string|int|null $id The ID assigned by the server.
     * @param string|null $eTag The ETag assigned by the server.
     * @return self
     */
    public function markAsPersisted(string|int|null $id, ?string $eTag): self
    {
        if ($id !== null) {
            $this->id = $id;
        }
        $this->eTag = $eTag;
        $this->isNewFlag = false;
        return $this;
    }
}