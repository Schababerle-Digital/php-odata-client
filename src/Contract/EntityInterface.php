<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

/**
 * Interface EntityInterface
 *
 * Repräsentiert eine einzelne Client-Entität.
 * Stellt Methoden zum Zugriff auf Eigenschaften und Metadaten der Entität bereit.
 */
interface EntityInterface
{
    /**
     * Gibt den eindeutigen Identifikator (ID) der Entität zurück.
     * Die ID kann ein String, eine Ganzzahl oder null sein (z.B. wenn die Entität neu ist).
     *
     * @return string|int|null
     */
    public function getId(): string|int|null;

    /**
     * Gibt den Client-Entitätstyp als String zurück (z.B. "Product", "Customer").
     * Dies kann nützlich sein, um den Kontext der Entität zu verstehen.
     *
     * @return string
     */
    public function getEntityType(): string;

    /**
     * Ruft den Wert einer bestimmten Eigenschaft der Entität ab.
     *
     * @param string $name Der Name der Eigenschaft.
     * @return mixed Der Wert der Eigenschaft oder null, falls die Eigenschaft nicht existiert.
     */
    public function getProperty(string $name): mixed;

    /**
     * Setzt den Wert einer bestimmten Eigenschaft der Entität.
     *
     * @param string $name Der Name der Eigenschaft.
     * @param mixed $value Der zu setzende Wert.
     * @return self Ermöglicht Method Chaining.
     */
    public function setProperty(string $name, mixed $value): self;

    /**
     * Gibt alle Daten-Eigenschaften der Entität als assoziatives Array zurück.
     * Schlüssel sind die Eigenschaftsnamen, Werte sind die Eigenschaftswerte.
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array;

    /**
     * Ruft eine Navigationseigenschaft (verknüpfte Entität oder Entitätensammlung) ab.
     *
     * @param string $name Der Name der Navigationseigenschaft.
     * @return EntityInterface|EntityCollectionInterface|null Die verknüpfte Entität, eine Sammlung
     * verknüpfter Entitäten oder null, falls nicht vorhanden oder nicht geladen.
     */
    public function getNavigationProperty(string $name): EntityInterface|EntityCollectionInterface|null;

    /**
     * Setzt eine Navigationseigenschaft (verknüpfte Entität oder Entitätensammlung).
     *
     * @param string $name Der Name der Navigationseigenschaft.
     * @param EntityInterface|EntityCollectionInterface|null $value Die zu setzende Entität oder Entitätensammlung.
     * @return self Ermöglicht Method Chaining.
     */
    public function setNavigationProperty(string $name, EntityInterface|EntityCollectionInterface|null $value): self;

    /**
     * Gibt alle geladenen Navigationseigenschaften als assoziatives Array zurück.
     *
     * @return array<string, EntityInterface|EntityCollectionInterface>
     */
    public function getNavigationProperties(): array;

    /**
     * Gibt das ETag (Entity Tag) der Entität zurück.
     * ETags werden für die optimistische Nebenläufigkeitskontrolle verwendet.
     *
     * @return string|null Das ETag oder null, falls nicht vorhanden.
     */
    public function getETag(): ?string;

    /**
     * Setzt das ETag (Entity Tag) der Entität.
     *
     * @param string|null $eTag Das zu setzende ETag.
     * @return self Ermöglicht Method Chaining.
     */
    public function setETag(?string $eTag): self;

    /**
     * Konvertiert die Entität (ihre Daten-Eigenschaften) in ein assoziatives Array.
     * Navigationseigenschaften werden hier typischerweise nicht tief serialisiert,
     * es sei denn, es ist explizit gewünscht und implementiert.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Prüft, ob die Entität als neu betrachtet wird (d.h. noch nicht im Client-Dienst persistiert wurde).
     * Dies kann z.B. durch das Fehlen einer ID oder eines ETags bestimmt werden.
     *
     * @return bool True, wenn die Entität neu ist, andernfalls false.
     */
    public function isNew(): bool;
}