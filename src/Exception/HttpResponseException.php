<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Wird geworfen, wenn eine HTTP-Antwort einen Fehlerstatuscode (z.B. 4xx oder 5xx) anzeigt.
 * Diese Exception kann die Request- und Response-Objekte für detailliertere Analysen enthalten.
 */
class HttpResponseException extends ODataRequestException
{
    protected ?RequestInterface $request = null;
    protected ?ResponseInterface $response = null;

    /**
     * @param string $message Die Fehlermeldung.
     * @param RequestInterface|null $request Das PSR-7 Request-Objekt, das den Fehler verursacht hat.
     * @param ResponseInterface|null $response Das PSR-7 Response-Objekt, das den Fehler enthält.
     * @param Throwable|null $previous Die vorherige Exception.
     * @param array<string, mixed>|null $odataError Optionale, geparste Client-Fehlerdetails aus dem Response-Body.
     */
    public function __construct(
        string $message = "",
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null,
        ?Throwable $previous = null,
        ?array $odataError = null
    ) {
        $code = $response ? $response->getStatusCode() : 0;
        parent::__construct($message, $code, $previous, $odataError);
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Gibt das PSR-7 Request-Objekt zurück, falls vorhanden.
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * Gibt das PSR-7 Response-Objekt zurück, falls vorhanden.
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}