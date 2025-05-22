# OData Client for PHP

[![PHP Version Require](http://poser.pugx.org/schababerledigital/odata-client/require/php)](https://packagist.org/packages/schababerledigital/odata-client)

**⚠️ Important note: This is an early alpha release! ⚠️**
**This software is in an early development phase (alpha). The API may change without notice and there may be bugs or incomplete features. Use in production environments is currently not recommended**.

A modern, easy-to-use PHP client library for interacting with OData services (version 2 and version 4 are supported). This library was developed with best practices in mind, including clear responsibilities, use of interfaces and PSR compatibility.

## Features

* Support for OData version 2 and version 4.
* Fluid query builder for creating complex queries (`$select`, `$filter`, `$expand`, `$orderby`, `$top`, `$skip`, `$count`, `$search`).
* Advanced `FilterBuilder` for programmatic creation of `$filter` expressions.
* Automatic parsing of OData responses into `Entity` and `EntityCollection` objects.
* Serialization of entities for create (POST) and update operations (PUT/PATCH/MERGE).
* Support for CRUD operations (Create, Read, Update, Delete).
* Calling OData functions and actions.
* Batch requests for OData V4 (JSON format).
* Flexible HTTP client layer through `HttpClientInterface` (standard implementation with Guzzle).
* Structured exception handling.

## Requirements

* PHP >= 8.0
* [Composer](https://getcomposer.org/)
* A PSR-18 compatible HTTP client implementation (e.g. `guzzlehttp/guzzle`)
* PSR-7 HTTP Message Interfaces (`psr/http-message`)

## Installation

You can install the library via Composer:

```bash
composer require schababerledigital/odata-client
```

## Quick start / basic usage

Here is a simple example of how you can use the OData V4 client:

```php
<?php

require 'vendor/autoload.php';

use SchababerleDigital\OData\Client\V4\Client as ODataV4Client;
use SchababerleDigital\OData\Http\GuzzleHttpClient; // Beispiel-HTTP-Client
use SchababerleDigital\OData\Client\V4\ResponseParser as ODataV4ResponseParser; // Beispiel-Parser
use SchababerleDigital\OData\Serializer\JsonSerializer; // Beispiel-Serializer
use SchababerleDigital\OData\QueryBuilder\FilterBuilder;
use SchababerleDigital\OData\Contract\QueryBuilderInterface;

// 1. HTTP-Client initialisieren (Guzzle als Beispiel)
$guzzleClient = new \GuzzleHttp\Client([
    // 'auth' => ['username', 'password'], // Optional: Authentifizierung
    // 'headers' => ['X-Custom-Header' => 'Value'] // Optionale Standard-Header
]);
$httpClient = new GuzzleHttpClient($guzzleClient);

// 2. Parser und Serializer initialisieren
$responseParser = new ODataV4ResponseParser();
$jsonSerializer = new JsonSerializer();

// 3. OData-Client instanziieren
$odataServiceUrl = '[https://services.odata.org/V4/TripPinServiceRW/](https://services.odata.org/V4/TripPinServiceRW/)'; // Beispiel-Dienst
$client = new ODataV4Client($httpClient, $responseParser, $jsonSerializer, $odataServiceUrl);

try {
    // Beispiel: Alle Personen abrufen und nur Name und E-Mails auswählen
    echo "Fetching all people...\n";
    $people = $client->find('People', function(QueryBuilderInterface $qb) {
        $qb->select(['UserName', 'FirstName', 'LastName', 'Emails'])
           ->top(5);
    });

    foreach ($people as $person) {
        echo "User: " . $person->getProperty('UserName') . " - Name: " . $person->getProperty('FirstName') . " " . $person->getProperty('LastName') . "\n";
        if (is_array($person->getProperty('Emails'))) {
            foreach ($person->getProperty('Emails') as $email) {
                echo "  Email: " . $email . "\n";
            }
        }
    }
    echo "Next Link for People: " . ($people->getNextLink() ?? 'None') . "\n\n";

    // Beispiel: Eine bestimmte Person abrufen
    $userNameToFetch = 'russellwhyte';
    echo "Fetching person: " . $userNameToFetch . "...\n";
    $person = $client->get('People', $userNameToFetch, function(QueryBuilderInterface $qb) {
        $qb->select(['FirstName', 'LastName', 'Gender']);
    });
    echo "Details for " . $person->getProperty('FirstName') . ":\n";
    print_r($person->getProperties());
    echo "\n";

    // Beispiel: FilterBuilder verwenden
    echo "Fetching people filtered by name...\n";
    $filter = FilterBuilder::new()
        ->where('FirstName')->startsWith('Russell')
        ->and()
        ->where('LastName')->equals('Whyte')
        ->build();

    $filteredPeople = $client->find('People', function(QueryBuilderInterface $qb) use ($filter) {
        $qb->filter($filter)->select(['UserName', 'FirstName', 'LastName']);
    });

    foreach ($filteredPeople as $filteredPerson) {
        echo "Filtered User: " . $filteredPerson->getProperty('UserName') . "\n";
    }

    // Weitere Beispiele (CRUD, Funktionen, Actions) könnten hier folgen...

} catch (\SchababerleDigital\OData\Exception\ODataException $e) {
    echo "OData Error: " . $e->getMessage() . "\n";
    if ($e instanceof \SchababerleDigital\OData\Exception\HttpResponseException && $e->getResponse()) {
        echo "Status Code: " . $e->getResponse()->getStatusCode() . "\n";
        // echo "Response Body: " . (string) $e->getResponse()->getBody() . "\n";
    }
    // Debugging: $e->getODataError() kann detailliertere Fehler vom Dienst enthalten, falls geparst
} catch (\Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

?>
```

### Using the OData V2 client

The use of the V2 client is analogous, simply replace the V4-specific classes with their V2 equivalents:

```php
use SchababerleDigital\OData\Client\V2\Client as ODataV2Client;
use SchababerleDigital\OData\Client\V2\ResponseParser as ODataV2ResponseParser;
// ...
$client = new ODataV2Client($httpClient, new ODataV2ResponseParser(), $jsonSerializer, $v2ServiceUrl);
// ...
```

### Create an entity (example for V4)

```php
try {
    $newPersonData = [
        'UserName' => 'newuser' . rand(1000, 9999),
        'FirstName' => 'Test',
        'LastName' => 'User',
        'Emails' => ['testuser@example.com'],
        'AddressInfo' => [ // Komplexer Typ
            [
                'Address' => '123 Main St',
                'City' => [
                    'Name' => 'MyCity',
                    'CountryRegion' => 'US',
                    'Region' => 'CA'
                ]
            ]
        ],
        // Um eine Verknüpfung zu einer existierenden Entität herzustellen (z.B. zu einem Trip):
        // 'Trips@odata.bind' => [ "Trips(0)" ] // Verknüpft diesen neuen User mit Trip mit ID 0
    ];

    // $newEntity = new \SchababerleDigital\OData\Client\Common\Entity('Person', $newPersonData);
    // $createdPerson = $client->create('People', $newEntity);
    // Besser: direkt Array übergeben, Client wandelt es um oder man erstellt eine spezifische Entitätsklasse.
    $createdPerson = $client->create('People', $newPersonData);

    echo "Created Person UserName: " . $createdPerson->getProperty('UserName') . " with ID: " . $createdPerson->getId() . "\n";

} catch (\SchababerleDigital\OData\Exception\ODataException $e) {
    // Fehlerbehandlung
    echo "Error creating person: " . $e->getMessage() . "\n";
    if ($e instanceof \SchababerleDigital\OData\Exception\HttpResponseException && $e->getResponse()) {
        echo "Raw Response: " . (string) $e->getResponse()->getBody() . "\n";
    }
}
```

## Configuration

### HTTP client options

You can configure the underlying HTTP client (e.g. Guzzle) with various options when it is instantiated, such as timeouts, authentication, proxy settings, etc. These are then used by the GuzzleHttpClient. These are then used by the GuzzleHttpClient.

```php
$guzzleClient = new \GuzzleHttp\Client([
    'timeout'  => 10.0, // Timeout in Sekunden
    'auth' => ['username', 'password', 'digest'], // Authentifizierung
    'proxy' => 'http://localhost:8123', // Proxy
    'headers' => [
        'X-Custom-Global-Header' => 'MeineAnwendung'
    ]
]);
$httpClient = new GuzzleHttpClient($guzzleClient);
// ... Client mit diesem $httpClient initialisieren
```

## Execute tests

To run the PHPUnit tests for this library:

1. Clone the repository (if not already done).
2. Install the dependencies with Composer:

```bash
composer install
```

3. Run PHPUnit from the main directory of the project:

```bash
./vendor/bin/phpunit
```

### Code Coverage

If you have installed and configured a code coverage driver such as Xdebug or PCOV, you can generate an HTML coverage report:

```bash
./vendor/bin/phpunit --coverage-html build/coverage/html
```

Then open <code>build/coverage/html/index.html</code> in your browser.

## Contributing

Contributions are welcome! Please create a fork of the repository, create a feature branch, commit your changes and create a pull request. Make sure that all tests are still successful.

## License

This library is released under the MIT license. See the LICENSE file for more information.

### Notes for you:

- Package name: In the composer.json you have used schababerledigital/odata-client. This name should also be used consistently here in the README, especially in the installation section.
- Authors: Update the authors section in composer.json and here if necessary.
- Examples: Expand the examples to show more features of your client (e.g. update, delete, functions/actions, batch requests in more detail).
- Specific configurations: If your client has special configuration options that go beyond the HTTP client options, document them.
- Badges: Once your project is on Packagist and you have set up CI/CD (like GitHub Actions, Travis CI) and code coverage services (like Coveralls, Codecov), you can replace the placeholders for the badges with real ones.