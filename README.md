# Shopify REST to GraphQL (PHP/Laravel Package)

This package designed to help developers migrate from Shopify's REST API to the more modern and efficient GraphQL API. The package provides a set of utility functions and endpoints that map REST API functionality to GraphQL queries and mutations, making it easier to transition your Laravel application to use Shopify's GraphQL API.
NOTE : This is experimental package 

## Features
 - Easy Integration: Simplifies the integration of Shopify's GraphQL API into your Laravel application.
 - Minimal Changes: Converts Shopify REST API payloads to GraphQL with minimal modifications, reducing development time and effort.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Usage](#usage)
- [GraphqlService Class](#graphqlservice-class)
- [Endpoints](#endpoints)
  - [Products](#products)
  - [Variants](#variants)
  - [Collections](#collections)
  - [Orders](#orders)
  - [Inventory](#inventory)
  - [ApplicationCharges](#applicationcharges)
  - [Metafields](#metafields)
  - [Fulfillments](#fulfillments)
  - [Webhooks](#webhooks)
  - [Themes](#themes)
  - [Other Endpoints](#other-endpoints)
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

## GraphqlService Class

The `GraphqlService` class is the core of this package and provides direct interaction with Shopify's GraphQL API.

### Available Methods

#### General GraphQL

- `graphqlQueryThalia(string $query, array $variables = [])`: Execute any GraphQL query with optional variables
- `graphQLQuery($query, $shop, $accessToken)`: Alternative method to execute GraphQL queries with different credentials

#### Products

- `graphqlPostProduct($params)`: Create a new product
- `graphqlPostProductWithVariants($params)`: Create a product with multiple variants
- `graphqlUpdateProduct($params)`: Update an existing product
- `graphqlGetProducts($params)`: Get a list of products with filtering options
- `graphqlGetProductsCount()`: Get the total count of products
- `graphqlGetProduct($shopifyid)`: Get a single product by ID
- `graphqlGetProductWithoutInventory($shopifyid)`: Get a product without inventory information
- `graphqlDeleteProduct($shopifyid)`: Delete a product
- `graphqlCheckProductOnShopify($shopifyid)`: Check if a product exists on Shopify
- `reOrderProductImages($params)`: Reorder product images
- `graphqlCreateProductImage($images, $productShopifyId)`: Add Product Image
- `graphqlDeleteProductImage(imageIds, $productShopifyId)`: Delete Product Image

#### Variants

- `graphqlDeleteVariant($shopifyid, $variantid)`: Delete a variant
- `graphqlGetProductVariants($shopifyid)`: Get all variants for a product
- `graphqlGetVariant($variantid)`: Get a single variant by ID
- `getProductIdFromVairant($variantid)`: Get product ID that a variant belongs to
- `graphqlUpdateVariant($shopifyId, $variantId, $params)`: Update a variant

#### Other

- `getCollectionHandle($collection_id)`: Get a collection's handle by ID

### Example: Creating a Product

```php
$productData = [
    'product' => [
        'title' => 'New Product',
        'body_html' => '<p>Product description</p>',
        'vendor' => 'My Vendor',
        'product_type' => 'Clothing',
        'tags' => 'tag1,tag2',
        'variants' => [
            [
                'price' => '19.99',
                'sku' => 'SKU123',
                'inventory_management' => 'shopify'
            ]
        ],
        'images' => [
            [
                'src' => 'https://example.com/image.jpg',
                'alt' => 'Product Image'
            ]
        ]
    ]
];

$response = $shopifyGraphql->graphqlPostProduct($productData);
```

### Example: Updating a Product

```php
$updateData = [
    'product' => [
        'id' => '1234567890',
        'title' => 'Updated Product Title',
        'tags' => 'updated,tags',
        'variants' => [
            [
                'id' => '9876543210',
                'price' => '29.99'
            ]
        ]
    ]
];

$response = $shopifyGraphql->graphqlUpdateProduct($updateData);
```

### Example: Getting Products

```php
$params = [
    'limit' => 10,
    'vendor' => 'Example Vendor',
    'published_status' => 'published',
    'fields' => 'id,title,variants,tags'
];

$products = $shopifyGraphql->graphqlGetProducts($params);
```
### Example: Add Product Image

```php
        $images = [
            [
                'url' => 'https://fastly.picsum.photos/id/365/200/300.jpg?hmac=n_4DxqK0o938eabBZRnEywWtPwgF2MKoTfnRmJ7vlKQ',
                'alt' => 'lorem ipsum',
            ]
        ];

$response = $shopifyGraphql->graphqlCreateProductImage($images, $productShopifyId);
```

### Example: Delete Product Image

```php
        $imageIds = [
            "4145546145154","5456564465234",
        ];


$response = $shopifyGraphql->graphqlDeleteProductImage($imageIds, $productShopifyId);
```

## Endpoints

The package provides endpoint classes that map to different Shopify resources. Each endpoint class offers methods that correspond to REST API operations but utilize GraphQL under the hood.

### Products

```php
use Thalia\ShopifyRestToGraphql\Endpoints\ProductsEndpoints;

$productsEndpoint = new ProductsEndpoints($shop, $accessToken);
```

- `getProducts($params)`: Get a list of products with filtering options
- `getProduct($productId)`: Get a single product by ID
- `productVariantsCount()`: Get the total count of product variants
- `deleteAllProductImages($productId)`: Delete all images for a product

### Variants

```php
use Thalia\ShopifyRestToGraphql\Endpoints\VariantsEndpoints;

$variantsEndpoint = new VariantsEndpoints($shop, $accessToken);
```

- `productVariantsBulkUpdate($shopifyId, $variantId, $params)`: Update a variant
- `productVariantsBulkDelete($shopifyId, $variantId)`: Delete a variant

### Collections

```php
use Thalia\ShopifyRestToGraphql\Endpoints\CollectionsEndpoints;

$collectionsEndpoint = new CollectionsEndpoints($shop, $accessToken);
```

- `getSmartCollections()`: Get a list of smart collections
- `getCollection($collectionId)`: Get a single collection by ID
- `getCustomCollections()`: Get a list of custom collections

### Orders

```php
use Thalia\ShopifyRestToGraphql\Endpoints\OrdersEndpoints;

$ordersEndpoint = new OrdersEndpoints($shop, $accessToken);
```

- `getOrders($params)`: Get a list of orders with filtering options
- `getOrder($orderId)`: Get a single order by ID
- `createOrder($params)`: Create a new order
- `updateOrder($orderId, $params)`: Update an existing order
- `cancelOrder($orderId, $params)`: Cancel an order
- `closeOrder($orderId)`: Close an order
- `reopenOrder($orderId)`: Reopen a closed order

### Inventory

```php
use Thalia\ShopifyRestToGraphql\Endpoints\InventoryEndpoints;

$inventoryEndpoint = new InventoryEndpoints($shop, $accessToken);
```

- `getInventoryLevels($params)`: Get inventory levels
- `adjustInventoryLevel($params)`: Adjust inventory level
- `getInventoryItem($inventoryItemId)`: Get inventory item by ID
- `updateInventoryItem($inventoryItemId, $params)`: Update inventory item

### ApplicationCharges

```php
use Thalia\ShopifyRestToGraphql\Endpoints\ApplicationChargesEndpoints;

$chargesEndpoint = new ApplicationChargesEndpoints($shop, $accessToken);
```

- `appPurchaseOneTimeCreate($params)`: Create a one-time application charge
- `currentAppInstallationForOneTime($chargeId)`: Get details of a one-time charge

### Metafields

```php
use Thalia\ShopifyRestToGraphql\Endpoints\MetafieldsEndpoints;

$metafieldsEndpoint = new MetafieldsEndpoints($shop, $accessToken);
```

- `getMetafields($params)`: Get metafields
- `getMetafield($metafieldId)`: Get a single metafield
- `createMetafield($params)`: Create a metafield
- `updateMetafield($metafieldId, $params)`: Update a metafield
- `deleteMetafield($metafieldId)`: Delete a metafield

### Fulfillments

```php
use Thalia\ShopifyRestToGraphql\Endpoints\FulfillmentsEndpoints;

$fulfillmentsEndpoint = new FulfillmentsEndpoints($shop, $accessToken);
```

- `createFulfillment($params)`: Create a fulfillment
- `updateFulfillment($fulfillmentId, $params)`: Update a fulfillment
- `getFulfillment($fulfillmentId)`: Get a single fulfillment
- `cancelFulfillment($fulfillmentId)`: Cancel a fulfillment

### Webhooks

```php
use Thalia\ShopifyRestToGraphql\Endpoints\WebhooksEndpoints;

$webhooksEndpoint = new WebhooksEndpoints($shop, $accessToken);
```

- `createWebhook($params)`: Create a webhook
- `getWebhooks($params)`: Get webhooks
- `getWebhook($webhookId)`: Get a single webhook
- `updateWebhook($webhookId, $params)`: Update a webhook
- `deleteWebhook($webhookId)`: Delete a webhook

### Themes

```php
use Thalia\ShopifyRestToGraphql\Endpoints\ThemesEndpoints;

$themesEndpoint = new ThemesEndpoints($shop, $accessToken);
```

- `getThemes()`: Get all themes
- `getTheme($themeId)`: Get a single theme
- `createTheme($params)`: Create a new theme
- `updateTheme($themeId, $params)`: Update a theme
- `deleteTheme($themeId)`: Delete a theme

### Other Endpoints

Additional endpoint classes include:

- `ShopEndpoints`: Shop-related operations
- `LocationsEndpoints`: Location management
- `DiscountsEndpoints`: Discount operations
- `ScriptTagsEndPoints`: Script tag operations
- `RecurringApplicationChargesEndpoints`: Recurring charge operations
- `OauthEndpoints`: OAuth operations
- `OauthScopeEndpoints`: OAuth scope operations
- `ShippingEndpoints`: Shipping operations

## Contributing
We welcome contributions! To contribute:
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature-branch`).
3. Commit your changes (`git commit -m 'Add new feature'`).
4. Push to your branch (`git push origin feature-branch`).
5. Open a Pull Request.

