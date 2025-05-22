<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

use Closure;
use SchababerleDigital\OData\Exception\ODataRequestException;
use SchababerleDigital\OData\Exception\EntityNotFoundException;

/**
 * Interface ODataClientInterface
 *
 * Definiert die Hauptschnittstelle für die Interaktion mit einem Client-Dienst.
 * Ermöglicht das Abrufen, Erstellen, Aktualisieren und Löschen von Entitäten
 * sowie das Aufrufen von Aktionen und Funktionen.
 */
interface ODataClientInterface
{
    /**
     * Ruft eine einzelne Entität anhand ihrer ID ab.
     *
     * @param string $entitySet Der Name des Entitäten-Sets (z.B. "Products").
     * @param string|int $id Die ID der abzurufenden Entität.
     * @param (Closure(QueryBuilderInterface): void)|null $queryConfigurator Eine optionale Closure,
     * die den QueryBuilder konfiguriert (z.B. für $select, $expand).
     * Beispiel: fn(QueryBuilderInterface $qb) => $qb->select('Name,Price')->expand('Category')
     * @return EntityInterface Die abgerufene Entität.
     * @throws ODataRequestException Bei Fehlern während der Anfrage oder des Parsens.
     * @throws EntityNotFoundException Wenn die Entität nicht gefunden wurde.
     */
    public function get(
        string $entitySet,
        string|int $id,
        ?Closure $queryConfigurator = null
    ): EntityInterface;

    /**
     * Ruft eine Sammlung von Entitäten aus einem Entitäten-Set ab.
     *
     * @param string $entitySet Der Name des Entitäten-Sets.
     * @param (Closure(QueryBuilderInterface): void)|null $queryConfigurator Eine optionale Closure,
     * die den QueryBuilder konfiguriert (z.B. für $filter, $top, $skip, $orderby).
     * @return EntityCollectionInterface Eine Sammlung der abgerufenen Entitäten.
     * @throws ODataRequestException Bei Fehlern während der Anfrage oder des Parsens.
     */
    public function find(
        string $entitySet,
        ?Closure $queryConfigurator = null
    ): EntityCollectionInterface;

    /**
     * Erstellt eine neue Entität in einem Entitäten-Set.
     *
     * @param string $entitySet Der Name des Entitäten-Sets.
     * @param EntityInterface|array<string, mixed> $data Die zu erstellende Entität oder ein Array mit ihren Daten.
     * @return EntityInterface Die vom Server zurückgegebene, erstellte Entität (kann zusätzliche Felder wie ID, ETag enthalten).
     * @throws ODataRequestException Bei Fehlern während der Anfrage oder des Parsens.
     */
    public function create(string $entitySet, EntityInterface|array $data): EntityInterface;

    /**
     * Aktualisiert eine bestehende Entität vollständig (HTTP PUT).
     *
     * @param string $entitySet Der Name des Entitäten-Sets.
     * @param string|int $id Die ID der zu aktualisierenden Entität.
     * @param EntityInterface|array<string, mixed> $data Die Entität mit den aktualisierten Daten oder ein Array.
     * @param string|null $eTag Optionales ETag für optimistische Nebenläufigkeitskontrolle.
     * Wenn null, wird versucht, das ETag von $data (falls EntityInterface) zu verwenden.
     * @return EntityInterface Die vom Server zurückgegebene, aktualisierte Entität oder eine Repräsentation des Erfolgs.
     * Manche Client-Dienste geben bei PUT 204 No Content zurück. Die Implementierung muss dies behandeln.
     * @throws ODataRequestException Bei Fehlern.
     * @throws EntityNotFoundException Wenn die Entität nicht gefunden wurde.
     */
    public function update(
        string $entitySet,
        string|int $id,
        EntityInterface|array $data,
        ?string $eTag = null
    ): EntityInterface; // Oder bool/void je nach Präferenz und Serververhalten

    /**
     * Aktualisiert eine bestehende Entität teilweise (HTTP PATCH/MERGE).
     *
     * @param string $entitySet Der Name des Entitäten-Sets.
     * @param string|int $id Die ID der zu aktualisierenden Entität.
     * @param EntityInterface|array<string, mixed> $data Die Entität mit den zu ändernden Daten oder ein Array.
     * Nur die in $data enthaltenen Felder werden geändert.
     * @param string|null $eTag Optionales ETag für optimistische Nebenläufigkeitskontrolle.
     * @return EntityInterface Die vom Server zurückgegebene, aktualisierte Entität oder eine Repräsentation des Erfolgs.
     * @throws ODataRequestException Bei Fehlern.
     * @throws EntityNotFoundException Wenn die Entität nicht gefunden wurde.
     */
    public function merge(
        string $entitySet,
        string|int $id,
        EntityInterface|array $data,
        ?string $eTag = null
    ): EntityInterface; // Oder bool/void

    /**
     * Löscht eine Entität.
     *
     * @param string $entitySet Der Name des Entitäten-Sets.
     * @param string|int $id Die ID der zu löschenden Entität.
     * @param string|null $eTag Optionales ETag für optimistische Nebenläufigkeitskontrolle.
     * @return bool True bei Erfolg, false bei Misserfolg.
     * @throws ODataRequestException Bei Fehlern.
     * @throws EntityNotFoundException Wenn die Entität nicht gefunden wurde.
     */
    public function delete(string $entitySet, string|int $id, ?string $eTag = null): bool;

    /**
     * Ruft eine Client-Funktion auf.
     *
     * @param string $functionName Der Name der Funktion.
     * @param array<string, mixed> $parameters Parameter für die Funktion.
     * @param string|null $bindingEntitySet Optionaler Entitäten-Set-Name, an den die Funktion gebunden ist.
     * @param string|int|null $bindingEntityId Optionale ID der Entität, an die die Funktion gebunden ist.
     * @param (Closure(QueryBuilderInterface): void)|null $queryConfigurator Optionale Query-Optionen für das Ergebnis der Funktion.
     * @return mixed Das Ergebnis der Funktion (kann Entity, Collection, primitiver Wert, komplexer Typ sein).
     * @throws ODataRequestException Bei Fehlern.
     */
    public function callFunction(
        string $functionName,
        array $parameters = [],
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null,
        ?Closure $queryConfigurator = null
    ): mixed;

    /**
     * Führt eine Client-Aktion aus.
     *
     * @param string $actionName Der Name der Aktion.
     * @param array<string, mixed> $parameters Parameter für die Aktion (werden typischerweise im Body gesendet).
     * @param string|null $bindingEntitySet Optionaler Entitäten-Set-Name, an den die Aktion gebunden ist.
     * @param string|int|null $bindingEntityId Optionale ID der Entität, an die die Aktion gebunden ist.
     * @return mixed Das Ergebnis der Aktion.
     * @throws ODataRequestException Bei Fehlern.
     */
    public function callAction(
        string $actionName,
        array $parameters = [],
        ?string $bindingEntitySet = null,
        string|int|null $bindingEntityId = null
    ): mixed;

    /**
     * Führt eine Batch-Anfrage aus.
     *
     * @param array $requests Eine Sammlung von Einzelanfragen, die im Batch ausgeführt werden sollen.
     * Die Struktur dieser Anfragen muss definiert werden (z.B. als Objekte).
     * @return array Die Ergebnisse der einzelnen Batch-Operationen.
     * @throws ODataRequestException Bei Fehlern in der Batch-Verarbeitung.
     */
    public function executeBatch(array $requests): array; // Ggf. ein BatchResponseInterface

    /**
     * Ruft das Metadaten-Dokument ($metadata) des Client-Dienstes ab.
     *
     * @return string Der rohe XML-Inhalt des Metadaten-Dokuments.
     * @throws ODataRequestException Bei Fehlern.
     */
    public function getMetadataDocument(): string;

    /**
     * Ruft das Service-Dokument des Client-Dienstes ab und parst es.
     * Das Service-Dokument listet die verfügbaren Entitäten-Sets auf.
     *
     * @return array<string, mixed> Eine strukturierte Repräsentation des Service-Dokuments.
     * @throws ODataRequestException Bei Fehlern.
     */
    public function getServiceDocument(): array; // Ggf. ein ServiceDocumentInterface

    /**
     * Erstellt eine neue Instanz des QueryBuilders, optional für einen bestimmten Entitäten-Set.
     *
     * @param string|null $entitySet Optional der Name des Entitäten-Sets, für den der QueryBuilder gilt.
     * @return QueryBuilderInterface
     */
    public function createQueryBuilder(?string $entitySet = null): QueryBuilderInterface;
}