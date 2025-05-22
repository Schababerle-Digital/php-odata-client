<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

/**
 * Interface SerializerInterface
 *
 * Definiert Methoden zum Serialisieren von PHP-Objekten (insbesondere Entitäten)
 * in ein Client-konformes Format (z.B. JSON) für Request-Bodies.
 */
interface SerializerInterface
{
    /**
     * Serialisiert eine einzelne Entität in einen String (z.B. JSON).
     *
     * @param EntityInterface $entity Die zu serialisierende Entität.
     * @return string Der serialisierte String.
     * @throws \SchababerleDigital\OData\Exception\ODataRequestException Wenn die Serialisierung fehlschlägt.
     */
    public function serializeEntity(EntityInterface $entity): string;

    /**
     * Serialisiert eine Sammlung von Requests für eine $batch-Anfrage.
     * Dieses Feature ist fortgeschrittener.
     *
     * @param array $requests Eine Sammlung von Request-Objekten oder -Definitionen für den Batch.
     * Jedes Element im Array repräsentiert eine einzelne Operation im Batch.
     * @return string Der serialisierte Batch-Request-Body.
     * @throws \SchababerleDigital\OData\Exception\ODataRequestException Wenn die Serialisierung fehlschlägt.
     */
    public function serializeBatch(array $requests): string;

    /**
     * Gibt den Content-Type Header-Wert zurück, der für die von diesem Serializer
     * erzeugten Payloads verwendet werden soll.
     *
     * Beispiel: "application/json;odata.metadata=minimal", "application/json"
     *
     * @return string Der Content-Type String.
     */
    public function getContentType(): string;
}