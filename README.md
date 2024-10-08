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
To post product via graphql using REST API payload

    use Thalia\ShopifyRestToGraphql\GraphqlService; 

    $shopifygl = new GraphqlService();
    $product = $shopifygl->graphqlPostProduct($params,$shop,$accesstoken);

To update product via graphql using REST API payload

    use Thalia\ShopifyRestToGraphql\GraphqlService; 

    $shopifygl = new GraphqlService();
    $product = $shopifygl->graphqlUpdateProduct($params,$shop,$accesstoken);