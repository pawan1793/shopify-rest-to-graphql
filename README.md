# Shopify REST to GraphQL Processor

Shopify REST to GraphQL Processor is a Laravel package that simplifies the process of converting Shopify REST API payloads into GraphQL queries with minimal modifications. This package enables seamless integration of Shopify's GraphQL API into your Laravel application.

NOTE :: This is experimental package 

## Features
 - Easy Integration: Simplifies the integration of Shopify's GraphQL API into your Laravel application.
 - Minimal Changes: Converts Shopify REST API payloads to GraphQL with minimal modifications, reducing development time and effort.

## Installation
Step 1: Install the Package
    You can install the package via Composer:
    
    composer require thalia/shopify-rest-to-graphql

Step 2: Publish the Configuration
    If you need to customize the package configuration, you can publish the configuration file:

    php artisan vendor:publish 
    
    --provider="Thalia\ShopifyRestToGraphql\ShopifyRestToGraphqlServiceProvider"
Step 3: Register the Service Provider
    If you are using Laravel 5.5+ with package auto-discovery, the service provider will be registered automatically. For earlier versions, add the service provider to the providers array in config/app.php:

    'providers' => [
        // ...
        Thalia\ShopifyRestToGraphql\ShopifyRestToGraphqlServiceProvider::class,
    ];


## How to use

## How to use graphqlQueryThalia Function 
Description
The graphqlQueryThalia function sends a GraphQL query to an API endpoint, with optional variables, and returns the decoded JSON response as an associative array. In case of any errors, it handles exceptions and returns a structured error message.

This function is designed to execute GraphQL queries and mutations and handle potential errors gracefully by returning error details in a predictable format.

    Parameters

    string $query
    Type: string
    Required: Yes
    Description: The GraphQL query string that defines the operation to be performed on the server (e.g., fetching or mutating data).

    array $variables (optional)
    Type: array
    Required: No
    Default: []
    Description: An associative array of variables that can be passed to the GraphQL query. This allows for dynamic and flexible queries. If no variables are needed, this can be an empty array.


Example Usage
1. Basic Query Without Variables

    $query = 'query { shop { name email } }';
    $variables = [];

    $response = $this->graphqlQueryThalia($query, $variables);

    if (isset($response['errors'])) {
        echo "Error: " . $response['errors']['message'];
    } else {
        print_r($response);
    }

2. Query With Variables

    $query = 'mutation createProduct($title: String!) { productCreate(input: { title: $title }) { product { id      title } } }';
    
    $variables = [
        'title' => 'New Product'
    ];

    $response = $this->graphqlQueryThalia($query, $variables);

    if (isset($response['errors'])) {
        echo "Error: " . $response['errors']['message'];
    } else {
        print_r($response);
    }
