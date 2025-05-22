<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Exception;

use Throwable;

/**
 * Wird geworfen, wenn beim Serialisieren einer Client-Anfrage ein Fehler auftritt.
 */
class SerializationException extends ODataRequestException
{
    /**
     * Die Daten, die nicht serialisiert werden konnten.
     * @var mixed|null
     */
    protected mixed $dataToSerialize = null;

    /**
     * @param string $message Die Fehlermeldung.
     * @param mixed|null $dataToSerialize Die Daten, die nicht serialisiert werden konnten.
     * @param int $code Der Fehlercode.
     * @param Throwable|null $previous Die vorherige Exception.
     * @param array<string, mixed>|null $odataError Optionale, geparste Client-Fehlerdetails.
     */
    public function __construct(
        string $message = "Failed to serialize Client request.",
        mixed $dataToSerialize = null,
        int $code = 0,
        ?Throwable $previous = null,
        ?array $odataError = null
    ) {
        parent::__construct($message, $code, $previous, $odataError);
        $this->dataToSerialize = $dataToSerialize;
    }

    /**
     * Gibt die Daten zurück, die nicht serialisiert werden konnten.
     */
    public function getDataToSerialize(): mixed
    {
        return $this->dataToSerialize;
    }
}