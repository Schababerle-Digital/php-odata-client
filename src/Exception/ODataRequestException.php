<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Exception;

use Throwable;

/**
 * Wird geworfen, wenn bei der Verarbeitung einer Client-Anfrage ein allgemeiner Fehler auftritt,
 * oder wenn der Client-Dienst einen Fehler meldet, der nicht spezifischer behandelt wird.
 */
class ODataRequestException extends ODataException
{
    /**
     * Die geparste Fehlerantwort vom Client-Dienst, falls verfügbar.
     * @var array<string, mixed>|null
     */
    protected ?array $odataError = null;

    /**
     * @param string $message Die Fehlermeldung.
     * @param int $code Der Fehlercode.
     * @param Throwable|null $previous Die vorherige Exception, falls vorhanden (für Exception Chaining).
     * @param array<string, mixed>|null $odataError Optionale, geparste Fehlerdetails vom Client-Dienst.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        ?array $odataError = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->odataError = $odataError;
    }

    /**
     * Gibt die geparsten Fehlerdetails vom Client-Dienst zurück, falls vorhanden.
     *
     * @return array<string, mixed>|null
     */
    public function getODataError(): ?array
    {
        return $this->odataError;
    }
}