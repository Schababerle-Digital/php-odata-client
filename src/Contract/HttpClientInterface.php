<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Interface HttpClientInterface
 *
 * Definiert die Methoden für einen HTTP-Client, der Anfragen an einen Client-Dienst sendet.
 * Die Verwendung von PSR-7 Interfaces (insbesondere ResponseInterface) wird für die Interoperabilität empfohlen.
 */
interface HttpClientInterface
{
    /**
     * Sendet eine HTTP-Anfrage.
     *
     * @param string $method Die HTTP-Methode (z.B. 'GET', 'POST', 'PUT', 'DELETE', 'PATCH').
     * @param string $uri Der URI, an den die Anfrage gesendet wird.
     * @param array<string, mixed> $options Zusätzliche Optionen für die Anfrage.
     * Kann Header, Body, Query-Parameter etc. enthalten.
     * Beispiel:
     * [
     * 'headers' => ['Authorization' => 'Bearer ...', 'Accept' => 'application/json'],
     * 'query' => ['param1' => 'value1'],
     * 'body' => '{"key":"value"}', // oder ein StreamInterface
     * 'timeout' => 10, // Timeout in Sekunden
     * ]
     * @return ResponseInterface Die PSR-7 HTTP-Antwort.
     * @throws ClientExceptionInterface Wenn die Anfrage nicht gesendet werden konnte.
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface;

    /**
     * Sendet eine GET-Anfrage.
     *
     * @param string $uri Der URI, an den die Anfrage gesendet wird.
     * @param array<string, string|string[]> $headers Optionale HTTP-Header.
     * @param array<string, mixed> $query Optionale Query-Parameter.
     * @return ResponseInterface Die PSR-7 HTTP-Antwort.
     * @throws ClientExceptionInterface Wenn die Anfrage nicht gesendet werden konnte.
     */
    public function get(string $uri, array $headers = [], array $query = []): ResponseInterface;

    /**
     * Sendet eine POST-Anfrage.
     *
     * @param string $uri Der URI, an den die Anfrage gesendet wird.
     * @param mixed $body Der Body der Anfrage (z.B. ein String, ein StreamInterface oder ein Array, das serialisiert wird).
     * @param array<string, string|string[]> $headers Optionale HTTP-Header.
     * @return ResponseInterface Die PSR-7 HTTP-Antwort.
     * @throws ClientExceptionInterface Wenn die Anfrage nicht gesendet werden konnte.
     */
    public function post(string $uri, mixed $body, array $headers = []): ResponseInterface;

    /**
     * Sendet eine PUT-Anfrage.
     *
     * @param string $uri Der URI, an den die Anfrage gesendet wird.
     * @param mixed $body Der Body der Anfrage.
     * @param array<string, string|string[]> $headers Optionale HTTP-Header.
     * @return ResponseInterface Die PSR-7 HTTP-Antwort.
     * @throws ClientExceptionInterface Wenn die Anfrage nicht gesendet werden konnte.
     */
    public function put(string $uri, mixed $body, array $headers = []): ResponseInterface;

    /**
     * Sendet eine PATCH-Anfrage.
     *
     * @param string $uri Der URI, an den die Anfrage gesendet wird.
     * @param mixed $body Der Body der Anfrage.
     * @param array<string, string|string[]> $headers Optionale HTTP-Header.
     * @return ResponseInterface Die PSR-7 HTTP-Antwort.
     * @throws ClientExceptionInterface Wenn die Anfrage nicht gesendet werden konnte.
     */
    public function patch(string $uri, mixed $body, array $headers = []): ResponseInterface;

    /**
     * Sendet eine DELETE-Anfrage.
     *
     * @param string $uri Der URI, an den die Anfrage gesendet wird.
     * @param array<string, string|string[]> $headers Optionale HTTP-Header.
     * @return ResponseInterface Die PSR-7 HTTP-Antwort.
     * @throws ClientExceptionInterface Wenn die Anfrage nicht gesendet werden konnte.
     */
    public function delete(string $uri, array $headers = []): ResponseInterface;
}