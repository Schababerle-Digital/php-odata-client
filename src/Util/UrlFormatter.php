<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Util;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Provides utility methods for formatting and manipulating Client URLs and their components.
 */
class UrlFormatter
{
    /**
     * Encodes a single primitive value for use in an Client URL key segment or literal.
     * Handles strings, integers, booleans, and DateTimeInterface objects.
     *
     * @param string|int|bool|DateTimeInterface|float|null $value The value to encode.
     * @return string The Client string representation of the value.
     * @throws InvalidArgumentException If the value type is not supported for key encoding.
     */
    public static function encodePrimitiveValue(string|int|bool|DateTimeInterface|float|null $value): string
    {
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null'; // Client V4 literal for null
        }
        if ($value instanceof DateTimeInterface) {
            // Client V4 expects ISO 8601 format for DateTimeOffset literals in URLs
            // Example: 2000-12-12T12:00:00Z
            return $value->format('Y-m-d\TH:i:s\Z'); // UTC Zulu time
        }

        throw new InvalidArgumentException('Unsupported value type for Client key/literal encoding: ' . gettype($value));
    }

    /**
     * Encodes an Client key segment for a URL path.
     * Handles simple keys (string, int) and composite keys (associative array).
     *
     * For simple keys: "keyValue" or 123 -> "'keyValue'" or "123"
     * For composite keys: ['Key1' => 'val1', 'Key2' => 123] -> "Key1='val1',Key2=123"
     *
     * @param string|int|array<string, string|int|bool|DateTimeInterface|float|null> $key The key value or an associative array for composite keys.
     * @return string The encoded key segment.
     */
    public static function encodeKeySegment(string|int|array $key): string
    {
        if (is_array($key)) {
            // Composite key: Key1=Value1,Key2=Value2
            $parts = [];
            foreach ($key as $propertyName => $propertyValue) {
                $parts[] = $propertyName . '=' . self::encodePrimitiveValue($propertyValue);
            }
            return implode(',', $parts);
        }
        // Simple key
        return self::encodePrimitiveValue($key);
    }

    /**
     * Combines multiple path segments into a single Client path string,
     * ensuring correct slash separation.
     *
     * Example: buildPath("Products", "(1)", "Category") results in "Products(1)/Category"
     * Example: buildPath("ServiceOp") results in "ServiceOp"
     *
     * @param string ...$segments The URL path segments to combine.
     * @return string The combined path string.
     */
    public static function buildPath(string ...$segments): string
    {
        $processedSegments = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            // Trim slashes from both ends of each segment to avoid double slashes later
            $processedSegments[] = trim($segment, '/');
        }
        // Join non-empty segments with a single slash
        return implode('/', array_filter($processedSegments, static fn(string $s) => $s !== ''));
    }

    /**
     * Combines a base URL with a relative path and optional query parameters.
     * Ensures correct slash handling between base URL and relative path.
     *
     * @param string $baseUrl The base URL (e.g., "https://server/service.svc").
     * @param string $relativePath The relative path (e.g., "Products", "Customers('ALFKI')/Orders").
     * @param array<string, mixed> $queryParameters Optional associative array of query parameters.
     * @return string The full URL.
     */
    public static function combineUrl(string $baseUrl, string $relativePath = '', array $queryParameters = []): string
    {
        $url = rtrim($baseUrl, '/') . '/';

        if ($relativePath !== '') {
            $url .= ltrim(self::buildPath($relativePath), '/'); // Use buildPath for relativePath consistency
        }

        if (!empty($queryParameters)) {
            // http_build_query with PHP_QUERY_RFC3986 for correct space encoding (%20)
            $url .= '?' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }
}