<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Wird geworfen, wenn eine angeforderte Entität nicht gefunden wurde (typischerweise HTTP 404).
 */
class EntityNotFoundException extends HttpResponseException
{
    protected ?string $entitySet = null;
    protected string|int|null $entityId = null;

    /**
     * @param string $message Die Fehlermeldung.
     * @param string|null $entitySet Der Name des Entitäten-Sets.
     * @param string|int|null $entityId Die ID der nicht gefundenen Entität.
     * @param RequestInterface|null $request Das PSR-7 Request-Objekt.
     * @param ResponseInterface|null $response Das PSR-7 Response-Objekt (sollte einen 404-Status haben).
     * @param Throwable|null $previous Die vorherige Exception.
     * @param array<string, mixed>|null $odataError Optionale, geparste Client-Fehlerdetails.
     */
    public function __construct(
        string $message = "Entity not found.",
        ?string $entitySet = null,
        string|int|null $entityId = null,
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null,
        ?Throwable $previous = null,
        ?array $odataError = null
    ) {
        // Sicherstellen, dass der Code 404 ist, wenn eine Response vorhanden ist, ansonsten Standard-Code
        $code = ($response && $response->getStatusCode() === 404) ? 404 : 0;
        if ($response && $response->getStatusCode() !== 404 && $message === "Entity not found.") {
            // Wenn eine Response da ist, aber nicht 404, die generische Nachricht anpassen
            $message = "Request failed for entity.";
        }

        parent::__construct($message, $request, $response, $previous, $odataError);
        // Den Code nach dem parent constructor setzen, falls er von der Response abweicht
        // oder die Response nicht 404 ist, aber wir es als EntityNotFound behandeln wollen.
        // In der Regel wird der parent constructor den Status Code der Response nehmen.
        // Wenn die Response aber z.B. 400 ist, aber wir wissen, dass es "Entity not found" bedeutet,
        // könnten wir den Code hier explizit setzen, aber Vorsicht ist geboten.
        // Für den Standardfall ist der Code der Response (oder 0) ausreichend.
        if ($code !== 0 && $this->code === 0) { // Falls der Parent den Code nicht von der Response genommen hat
            $this->code = $code;
        }


        $this->entitySet = $entitySet;
        $this->entityId = $entityId;
    }

    /**
     * Gibt den Namen des Entitäten-Sets zurück, in dem die Entität nicht gefunden wurde.
     */
    public function getEntitySet(): ?string
    {
        return $this->entitySet;
    }

    /**
     * Gibt die ID der Entität zurück, die nicht gefunden wurde.
     */
    public function getEntityId(): string|int|null
    {
        return $this->entityId;
    }
}