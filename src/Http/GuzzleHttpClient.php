<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use SchababerleDigital\OData\Contract\HttpClientInterface;
use SchababerleDigital\OData\Exception\HttpResponseException; // Assuming we'll use this for re-throwing

/**
 * An HTTP client implementation using Guzzle.
 */
class GuzzleHttpClient implements HttpClientInterface
{
    protected GuzzleClientInterface $client;

    /**
     * @param GuzzleClientInterface|null $client An optional Guzzle client instance. If null, a new one will be created.
     * @param array<string, mixed> $guzzleConfig Default configuration for the Guzzle client if one is created internally.
     */
    public function __construct(?GuzzleClientInterface $client = null, array $guzzleConfig = [])
    {
        $this->client = $client ?? new GuzzleClient($guzzleConfig);
    }

    /**
     * {@inheritDoc}
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            // The Psr\Http\Client\ClientExceptionInterface is a marker interface.
            // GuzzleException itself does not implement ClientExceptionInterface directly
            // but its specific exceptions like RequestException do.
            // For simplicity here, we might re-throw a more generic or a custom HTTP exception.
            // A more sophisticated error handling might inspect $e further.
            throw new HttpResponseException(
                $e->getMessage(),
                null, // Request can be obtained from Guzzle's RequestException if $e is one
                $e instanceof \GuzzleHttp\Exception\RequestException ? $e->getResponse() : null,
                $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uri, array $headers = [], array $query = []): ResponseInterface
    {
        return $this->request('GET', $uri, [
            'headers' => $headers,
            'query' => $query,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $uri, mixed $body, array $headers = []): ResponseInterface
    {
        $options = ['headers' => $headers];

        if (is_resource($body) || is_string($body) || $body instanceof \Psr\Http\Message\StreamInterface) {
            $options['body'] = $body;
        } elseif (is_array($body)) {
            // Assuming JSON for array bodies by default, Guzzle's 'json' option handles this.
            // If it's form params, 'form_params' should be used.
            // The HttpClientInterface is generic, so an implementation detail.
            // For a generic Client client, JSON is most common.
            $options['json'] = $body;
        } else {
            $options['body'] = $body; // Or throw an InvalidArgumentException if type is not supported
        }

        return $this->request('POST', $uri, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $uri, mixed $body, array $headers = []): ResponseInterface
    {
        $options = ['headers' => $headers];

        if (is_resource($body) || is_string($body) || $body instanceof \Psr\Http\Message\StreamInterface) {
            $options['body'] = $body;
        } elseif (is_array($body)) {
            $options['json'] = $body;
        } else {
            $options['body'] = $body;
        }

        return $this->request('PUT', $uri, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function patch(string $uri, mixed $body, array $headers = []): ResponseInterface
    {
        $options = ['headers' => $headers];

        if (is_resource($body) || is_string($body) || $body instanceof \Psr\Http\Message\StreamInterface) {
            $options['body'] = $body;
        } elseif (is_array($body)) {
            $options['json'] = $body;
        } else {
            $options['body'] = $body;
        }

        return $this->request('PATCH', $uri, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $uri, array $headers = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, [
            'headers' => $headers,
        ]);
    }
}