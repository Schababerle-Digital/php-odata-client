<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Exception;

use Throwable;

/**
 * Wird geworfen, wenn beim Parsen einer Client-Antwort ein Fehler auftritt.
 */
class ParseException extends ODataRequestException
{
    /**
     * Der rohe Inhalt, der nicht geparst werden konnte.
     * @var string|null
     */
    protected ?string $rawContent = null;

    /**
     * @param string $message Die Fehlermeldung.
     * @param string|null $rawContent Der Inhalt, der das Parsen fehlschlagen ließ.
     * @param int $code Der Fehlercode.
     * @param Throwable|null $previous Die vorherige Exception.
     * @param array<string, mixed>|null $odataError Optionale, geparste Client-Fehlerdetails.
     */
    public function __construct(
        string $message = "Failed to parse Client response.",
        ?string $rawContent = null,
        int $code = 0,
        ?Throwable $previous = null,
        ?array $odataError = null
    ) {
        parent::__construct($message, $code, $previous, $odataError);
        $this->rawContent = $rawContent;
    }

    /**
     * Gibt den rohen Inhalt zurück, der nicht geparst werden konnte.
     */
    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }
}