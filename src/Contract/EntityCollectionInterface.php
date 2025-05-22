<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

use Countable;
use IteratorAggregate;
use Traversable; // Notwendig für den Return-Type von getIterator()

/**
 * Interface EntityCollectionInterface
 *
 * Repräsentiert eine Sammlung von Client-Entitäten.
 * Sollte iterierbar und zählbar sein und kann Metadaten wie Links für Paginierung enthalten.
 *
 * @template TEntity of EntityInterface
 * @extends IteratorAggregate<int, TEntity>
 */
interface EntityCollectionInterface extends Countable, IteratorAggregate
{
    /**
     * Fügt eine Entität zur Sammlung hinzu.
     *
     * @param EntityInterface $entity Die hinzuzufügende Entität.
     * @psalm-param TEntity $entity
     * @return self
     */
    public function add(EntityInterface $entity): self;

    /**
     * Entfernt eine Entität aus der Sammlung.
     * Es könnte sinnvoller sein, dies über die ID oder eine Referenz zu implementieren.
     *
     * @param EntityInterface $entity Die zu entfernende Entität.
     * @psalm-param TEntity $entity
     * @return self
     */
    public function remove(EntityInterface $entity): self;

    /**
     * Gibt alle Entitäten in der Sammlung als Array zurück.
     *
     * @return array<int, EntityInterface>
     * @psalm-return array<int, TEntity>
     */
    public function all(): array;

    /**
     * Prüft, ob die Sammlung leer ist.
     *
     * @return bool True, wenn die Sammlung keine Entitäten enthält, andernfalls false.
     */
    public function isEmpty(): bool;

    /**
     * Gibt die erste Entität in der Sammlung zurück.
     *
     * @return EntityInterface|null Die erste Entität oder null, wenn die Sammlung leer ist.
     * @psalm-return TEntity|null
     */
    public function first(): ?EntityInterface;

    /**
     * Gibt die letzte Entität in der Sammlung zurück.
     *
     * @return EntityInterface|null Die letzte Entität oder null, wenn die Sammlung leer ist.
     * @psalm-return TEntity|null
     */
    public function last(): ?EntityInterface;

    /**
     * Gibt die Gesamtanzahl der Entitäten in der Ergebnismenge zurück,
     * wie sie vom Server mitgeteilt wurde (z.B. über @odata.count).
     * Dies kann von der Anzahl der aktuell in der Sammlung befindlichen Entitäten abweichen (Paginierung).
     *
     * @return int|null Die Gesamtanzahl oder null, wenn nicht bekannt.
     */
    public function getTotalCount(): ?int;

    /**
     * Setzt die Gesamtanzahl der Entitäten.
     *
     * @param int|null $count Die Gesamtanzahl.
     * @return self
     */
    public function setTotalCount(?int $count): self;

    /**
     * Gibt den Link zur nächsten Seite der Ergebnisse zurück (Paginierung).
     * Entspricht oft dem @odata.nextLink in der Client-Antwort.
     *
     * @return string|null Den Link zur nächsten Seite oder null, wenn nicht vorhanden.
     */
    public function getNextLink(): ?string;

    /**
     * Setzt den Link zur nächsten Seite der Ergebnisse.
     *
     * @param string|null $link Der Link zur nächsten Seite.
     * @return self
     */
    public function setNextLink(?string $link): self;

    /**
     * Gibt den Delta-Link zurück, falls vorhanden.
     * Delta-Links werden für die Nachverfolgung von Änderungen verwendet.
     * Entspricht oft dem @odata.deltaLink in der Client-Antwort.
     *
     * @return string|null Den Delta-Link oder null, wenn nicht vorhanden.
     */
    public function getDeltaLink(): ?string;

    /**
     * Setzt den Delta-Link.
     *
     * @param string|null $link Der Delta-Link.
     * @return self
     */
    public function setDeltaLink(?string $link): self;

    /**
     * Erforderlich durch IteratorAggregate.
     * Erlaubt das Iterieren über die Sammlung mit foreach.
     *
     * @return Traversable<int, EntityInterface>
     * @psalm-return Traversable<int, TEntity>
     */
    public function getIterator(): Traversable;

    /**
     * Erforderlich durch Countable.
     * Gibt die Anzahl der Entitäten in der aktuellen Sammlung zurück (nicht unbedingt die Gesamtanzahl).
     *
     * @return int
     */
    public function count(): int;
}