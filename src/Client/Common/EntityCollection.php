<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\Common;

use ArrayIterator;
use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;
use Traversable;

/**
 * A basic implementation of an Client entity collection.
 *
 * @template TEntity of EntityInterface
 * @implements EntityCollectionInterface<TEntity>
 */
class EntityCollection implements EntityCollectionInterface
{
    /** @var array<int, EntityInterface> */
    protected array $entities = [];
    protected ?int $totalCount = null;
    protected ?string $nextLink = null;
    protected ?string $deltaLink = null;

    /**
     * @param array<int, EntityInterface> $entities Initial array of entities.
     * @psalm-param array<int, TEntity> $entities
     */
    public function __construct(array $entities = [])
    {
        $this->entities = array_values($entities); // Ensure keys are numeric and sequential
    }

    /**
     * {@inheritDoc}
     * @psalm-param TEntity $entity
     */
    public function add(EntityInterface $entity): self
    {
        $this->entities[] = $entity;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @psalm-param TEntity $entity
     */
    public function remove(EntityInterface $entity): self
    {
        $this->entities = array_filter($this->entities, static fn(EntityInterface $existingEntity) => $existingEntity !== $entity);
        $this->entities = array_values($this->entities); // Re-index
        return $this;
    }

    /**
     * {@inheritDoc}
     * @psalm-return array<int, TEntity>
     */
    public function all(): array
    {
        return $this->entities;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->entities);
    }

    /**
     * {@inheritDoc}
     * @psalm-return TEntity|null
     */
    public function first(): ?EntityInterface
    {
        return $this->entities[0] ?? null;
    }

    /**
     * {@inheritDoc}
     * @psalm-return TEntity|null
     */
    public function last(): ?EntityInterface
    {
        $count = count($this->entities);
        return $count > 0 ? $this->entities[$count - 1] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalCount(): ?int
    {
        return $this->totalCount;
    }

    /**
     * {@inheritDoc}
     */
    public function setTotalCount(?int $count): self
    {
        $this->totalCount = $count;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getNextLink(): ?string
    {
        return $this->nextLink;
    }

    /**
     * {@inheritDoc}
     */
    public function setNextLink(?string $link): self
    {
        $this->nextLink = $link;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeltaLink(): ?string
    {
        return $this->deltaLink;
    }

    /**
     * {@inheritDoc}
     */
    public function setDeltaLink(?string $link): self
    {
        $this->deltaLink = $link;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @psalm-return Traversable<int, TEntity>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entities);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->entities);
    }
}