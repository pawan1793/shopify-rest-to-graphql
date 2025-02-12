# Shopify REST to GraphQL (PHP/Laravel Package)

This package designed to help developers migrate from Shopify's REST API to the more modern and efficient GraphQL API. The package provides a set of utility functions and endpoints that map REST API functionality to GraphQL queries and mutations, making it easier to transition your Laravel application to use Shopify's GraphQL API.
NOTE :: This is experimental package 

## Features
 - Easy Integration: Simplifies the integration of Shopify's GraphQL API into your Laravel application.
 - Minimal Changes: Converts Shopify REST API payloads to GraphQL with minimal modifications, reducing development time and effort.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Usage](#usage)
- [Endpoints](#endpoints)
  - [ApplicationCharges](#ApplicationCharges)
  - [Collections](#Collections)
- [Contributing](#contributing)


## Overview
Shopify's GraphQL API offers several advantages over the REST API, including more efficient data fetching, fewer requests, and more flexible queries. This Laravel package provides a seamless way to transition from the REST API to GraphQL by offering utility functions and endpoints that mimic REST API behavior but use GraphQL under the hood.

## Installation
To install the package in your Laravel application, follow these steps:

```sh
composer require thalia/shopify-rest-to-graphql
```

Publish the configuration file:

```sh
php artisan vendor:publish --provider="Thalia\ShopifyRestToGraphql\ShopifyRestToGraphqlServiceProvider"
```

## Usage
The package provides a GraphqlService class with methods to handle various Shopify GraphQL operations.

```php
use Thalia\ShopifyRestToGraphql\GraphqlService;

$shopifyGraphql = new GraphqlService($shop, $accessToken);
$product = $shopifyGraphql->graphqlPostProduct($params);
print_r($product);

```

Example Usage for calling graphql query
1. Basic Query Without Variables

    ```php
    $query = 'query { shop { name email } }';
    $variables = [];

    $response = $shopifyGraphql->graphqlQueryThalia($query, $variables);

    if (isset($response['errors'])) {
        echo "Error: " . $response['errors']['message'];
    } else {
        print_r($response);
    }
    ```


2. Query With Variables

    ```php
    $query = 'mutation createProduct($title: String!) { productCreate(input: { title: $title }) { product { id      title } } }';

    $variables = [
        'title' => 'New Product'
    ];

    $response = $shopifyGraphql->graphqlQueryThalia($query, $variables);

    if (isset($response['errors'])) {
        echo "Error: " . $response['errors']['message'];
    } else {
        print_r($response);
    }
    ```

## Endpoints
### ApplicationCharges
- `appPurchaseOneTimeCreate($params)`: Charges a shop for features or services one time. This type of charge is recommended for apps that aren't billed on a recurring basis. Test and demo shops aren't charged.
- `currentAppInstallationForOneTime($chargeId)`: Returns charge deatils based on chargeId.

### Collections
- `getSmartCollections()`: Returns a list of smart collections.
- `getCollection($collectionId))`: Returns collection by ID.

    ## Usage Example
    
    ```php
    use Thalia\ShopifyRestToGraphql\Endpoints\CollectionsEndpoints; 

    $collectionsEndpoint = new CollectionsEndpoints($shop, $accessToken);
    $collections = $collectionsEndpoint->getCustomCollections();
    print_r($collections);
    ```


## Contributing
We welcome contributions! To contribute:
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature-branch`).
3. Commit your changes (`git commit -m 'Add new feature'`).
4. Push to your branch (`git push origin feature-branch`).
5. Open a Pull Request.

