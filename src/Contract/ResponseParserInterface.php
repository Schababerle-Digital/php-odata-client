<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

/**
 * Interface ResponseParserInterface
 *
 * Definiert Methoden zum Parsen von Client HTTP-Antworten in PHP-Objekte oder -Strukturen.
 */
interface ResponseParserInterface
{
    /**
     * Parst den Body einer HTTP-Antwort, die eine Sammlung von Entitäten enthält.
     *
     * @param string $responseBody Der rohe Antwort-Body als String.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return EntityCollectionInterface Eine Sammlung von Entitäten.
     * @throws \SchababerleDigital\OData\Exception\ODataRequestException Wenn das Parsen fehlschlägt.
     */
    public function parseCollection(string $responseBody, array $headers = []): EntityCollectionInterface;

    /**
     * Parst den Body einer HTTP-Antwort, die eine einzelne Entität enthält.
     *
     * @param string $responseBody Der rohe Antwort-Body als String.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return EntityInterface Eine einzelne Entität.
     * @throws \SchababerleDigital\OData\Exception\ODataRequestException Wenn das Parsen fehlschlägt.
     */
    public function parseEntity(string $responseBody, array $headers = []): EntityInterface;

    /**
     * Parst den Body einer HTTP-Antwort, die einen einzelnen primitiven Wert oder einen komplexen Typ enthält.
     * Z.B. bei Abfragen wie /Users(1)/FirstName/$value
     *
     * @param string $responseBody Der rohe Antwort-Body als String.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return mixed Der geparste Wert.
     * @throws \SchababerleDigital\OData\Exception\ODataRequestException Wenn das Parsen fehlschlägt.
     */
    public function parseValue(string $responseBody, array $headers = []): mixed;

    /**
     * Parst den Body einer HTTP-Antwort, die nur eine Anzahl enthält (z.B. von einer $count Abfrage).
     *
     * @param string $responseBody Der rohe Antwort-Body als String.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return int Die Anzahl.
     * @throws \SchababerleDigital\OData\Exception\ODataRequestException Wenn das Parsen fehlschlägt.
     */
    public function parseCount(string $responseBody, array $headers = []): int;

    /**
     * Parst eine Client-Fehlerantwort.
     *
     * @param string $responseBody Der rohe Antwort-Body der Fehlerantwort.
     * @param int $statusCode Der HTTP-Statuscode der Antwort.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return array<string, mixed> Ein assoziatives Array, das die Fehlerdetails strukturiert darstellt.
     * Beispiel: ['code' => 'ErrorCode', 'message' => 'Error message', 'details' => [...]].
     */
    public function parseError(string $responseBody, int $statusCode, array $headers = []): array;

    /**
     * Extrahiert den Link zur nächsten Seite (@odata.nextLink) aus dem Antwort-Body oder den Headern.
     *
     * @param string $responseBody Der rohe Antwort-Body.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return string|null Den Link zur nächsten Seite oder null, falls nicht vorhanden.
     */
    public function extractNextLink(string $responseBody, array $headers = []): ?string;

    /**
     * Extrahiert den Delta-Link (@odata.deltaLink) aus dem Antwort-Body oder den Headern.
     *
     * @param string $responseBody Der rohe Antwort-Body.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return string|null Den Delta-Link oder null, falls nicht vorhanden.
     */
    public function extractDeltaLink(string $responseBody, array $headers = []): ?string;

    /**
     * Extrahiert die Inline-Anzahl (@odata.count) aus dem Antwort-Body.
     *
     * @param string $responseBody Der rohe Antwort-Body.
     * @param array<string, string|string[]> $headers Die Antwort-Header.
     * @return int|null Die Inline-Anzahl oder null, falls nicht vorhanden.
     */
    public function extractInlineCount(string $responseBody, array $headers = []): ?int;
}