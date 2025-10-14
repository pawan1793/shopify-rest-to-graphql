<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class ProductsEndpoints
{
    private $graphqlService;

    private $shopDomain;
    private $accessToken;

    public function __construct(string $shopDomain = null, string $accessToken = null)
    {

        if ($shopDomain === null || $accessToken === null) {
            throw new \InvalidArgumentException('Shop domain and access token must be provided.');
        }


        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;

        $this->graphqlService = new GraphqlService($this->shopDomain, $this->accessToken);

    }

    /** 
     * To get Products use this function.
     */
    public function getProducts($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/products?example=Retrieve+a+list+of+products
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/product#get-products
        */



        $query = '';

        if (isset($params['productid'])) {

            $query = "query:\"id:{$params['productid']}\"";

        } elseif (isset($params['collection_id'])) {

            $query = "query:\"collection_id:{$params['collection_id']}\"";

        }

        $productlimit = isset($params['limit']) ? $params['limit'] : 250;

        $productsquery = <<<GRAPHQL
                        query GetProducts {
                            products(first: $productlimit, $query) {
                                nodes {
                                    id
                                    title
                                    descriptionHtml
                                    vendor
                                    productType
                                    createdAt
                                    handle
                                    updatedAt
                                    publishedAt
                                    templateSuffix
                                    tags
                                    status
                                    options(first: 5) {
                                        id
                                        name
                                        position
                                        values
                                    }
                                    media(first: 100) {
                                        edges {
                                            node {
                                                ... on MediaImage {
                                                    id
                                                    image {
                                                        id
                                                        altText
                                                        width
                                                        height
                                                        originalSrc
                                                    }
                                                }
                                            }
                                        }                              
                                    }
                                    variants(first: 200) {
                                        edges {
                                            node {
                                                id
                                                title
                                                price
                                                position
                                                inventoryPolicy
                                                inventoryQuantity
                                                compareAtPrice
                                                createdAt
                                                updatedAt
                                                taxable
                                                barcode
                                                sku
                                                taxCode
                                                inventoryItem {
                                                    id
                                                    tracked
                                                    requiresShipping
                                                    countryCodeOfOrigin
                                                    measurement {
                                                        weight {
                                                            unit
                                                            value
                                                        }
                                                    }
                                                }
                                                image {
                                                    id
                                                    url
                                                }
                                                selectedOptions {
                                                    name
                                                    value
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($productsquery);

        if (isset($responseData['data']['products']['userErrors']) && !empty($responseData['data']['products']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['products']['userErrors']);

        } else {

            $responseData = $responseData['data']['products'];

            $products = array();

            foreach ($responseData['nodes'] as $pkey => $edge) {

                $product = array();
                $product['id'] = str_replace('gid://shopify/Product/', '', $edge['id']);
                $product['title'] = $edge['title'];
                $product['body_html'] = $edge['descriptionHtml'];
                $product['vendor'] = $edge['vendor'];
                $product['product_type'] = $edge['productType'];
                $product['created_at'] = $edge['createdAt'];
                $product['handle'] = $edge['handle'];
                $product['updated_at'] = $edge['updatedAt'];
                $product['published_at'] = $edge['publishedAt'];
                $product['template_suffix'] = $edge['templateSuffix'];
                $product['tags'] = implode(',', $edge['tags']);
                $product['status'] = $edge['status'];

                $product['variants'] = array();
                $product['options'] = array();
                $product['images'] = array();
                $product['image'] = array();

                foreach ($edge['variants']['edges'] as $key => $value) {

                    $variant = array();
                    $variant['id'] = str_replace('gid://shopify/ProductVariant/', '', $value['node']['id']);
                    $variant['product_id'] = $product['id'];
                    $variant['title'] = $value['node']['title'];
                    $variant['price'] = $value['node']['price'];
                    $variant['position'] = $value['node']['position'];
                    $variant['inventory_policy'] = $value['node']['inventoryPolicy'];
                    $variant['compare_at_price'] = $value['node']['compareAtPrice'];
                    $variant['option1'] = $value['node']['selectedOptions'][0]['value'];
                    $variant['option2'] = $value['node']['selectedOptions'][1]['value'] ?? null;
                    $variant['option3'] = $value['node']['selectedOptions'][2]['value'] ?? null;
                    $variant['created_at'] = $value['node']['createdAt'];
                    $variant['updated_at'] = $value['node']['updatedAt'];
                    $variant['taxable'] = $value['node']['taxable'];
                    $variant['barcode'] = $value['node']['barcode'];
                    if ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'KILOGRAMS') {
                        $variant['grams'] = round($value['node']['inventoryItem']['measurement']['weight']['value'] * 1000);
                        $variant['weight_unit'] = "kg";
                    } elseif ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'GRAMS') {
                        $variant['grams'] = $value['node']['inventoryItem']['measurement']['weight']['value'];
                        $variant['weight_unit'] = "g";
                    } elseif ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'OUNCES') {
                        $variant['grams'] = round($value['node']['inventoryItem']['measurement']['weight']['value'] * 28.3495);
                        $variant['weight_unit'] = "oz";
                    } elseif ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'POUNDS') {
                        $variant['grams'] = round($value['node']['inventoryItem']['measurement']['weight']['value'] * 453.592);
                        $variant['weight_unit'] = "lb";
                    }
                    $variant['inventory_management'] = $value['node']['inventoryItem']['tracked'] == 'true' ? 'shopify' : 'not managed';
                    $variant['requires_shipping'] = $value['node']['inventoryItem']['requiresShipping'];
                    $variant['sku'] = $value['node']['sku'];
                    $variant['weight'] = $value['node']['inventoryItem']['measurement']['weight']['value'];
                    $variant['inventory_item_id'] = str_replace('gid://shopify/InventoryItem/', '', $value['node']['inventoryItem']['id']);
                    $variant['inventory_quantity'] = $value['node']['inventoryQuantity'];
                    $variant['old_inventory_quantity'] = $value['node']['inventoryQuantity'];
                    $variant['image_id'] = isset($value['node']['image']) ? str_replace('gid://shopify/ProductImage/', '', $value['node']['image']['id']) : null;

                    $product['variants'][] = $variant;

                }

                foreach ($edge['options'] as $key => $value) {

                    $option = array();
                    $option['id'] = str_replace('gid://shopify/ProductOption/', '', $value['id']);
                    $option['product_id'] = $product['id'];
                    $option['name'] = $value['name'];
                    $option['position'] = $value['position'];
                    $option['values'] = $value['values'];

                    $product['options'][] = $option;

                }

                foreach ($edge['media']['edges'] as $key => $value) {

                    if (isset($value['node']['image'])) {

                        $image = array();
                        $image['id'] = str_replace('gid://shopify/ImageSource/', '', $value['node']['image']['id']);
                        $image['product_id'] = $product['id'];
                        $image['alt'] = $value['node']['image']['altText'];
                        $image['width'] = $value['node']['image']['width'];
                        $image['height'] = $value['node']['image']['height'];
                        $image['src'] = $value['node']['image']['originalSrc'];

                        $product['images'][] = $image;

                    }

                }

                if (!empty($product['images'])) {
                    $product['image'][] = $product['images'][0];
                }


                $products[] = $product;
            }

            return $products;

        }
    }

    /** 
     * To get Product By Id use this function.
     */
    public function getProduct($productId)
    {
        /*
            aphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/product?example=Retrieve+a+single+product
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/product#get-products-product-id
        */



        $productquery = <<<GRAPHQL
                query GetProduct {
                    product(id: "gid://shopify/Product/$productId") {
                        id
                        title
                        descriptionHtml
                        vendor
                        productType
                        createdAt
                        handle
                        updatedAt
                        publishedAt
                        templateSuffix
                        tags
                        status
                        options(first: 5) {
                            id
                            name
                            position
                            values
                        }
                        media(first: 100) {
                            edges {
                                node {
                                    ... on MediaImage {
                                        id
                                        image {
                                            id
                                            altText
                                            width
                                            height
                                            originalSrc
                                        }
                                    }
                                }
                            }                              
                        }
                        variants(first: 200) {
                            edges {
                                node {
                                    id
                                    title
                                    price
                                    position
                                    inventoryPolicy
                                    inventoryQuantity
                                    compareAtPrice
                                    createdAt
                                    updatedAt
                                    taxable
                                    barcode
                                    sku
                                    taxCode
                                    inventoryItem {
                                        id
                                        tracked
                                        requiresShipping
                                        countryCodeOfOrigin
                                        measurement {
                                            weight {
                                                unit
                                                value
                                            }
                                        }
                                    }
                                    image {
                                        id
                                        url
                                    }
                                    selectedOptions {
                                        name
                                        value
                                    }
                                }
                            }
                        }
                    }
                }
                GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($productquery);

        if (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['product']['userErrors']);

        } else {

            $edge = $responseData['data']['product'];

            $product = array();
            $product['id'] = str_replace('gid://shopify/Product/', '', $edge['id']);
            $product['title'] = $edge['title'];
            $product['body_html'] = $edge['descriptionHtml'];
            $product['vendor'] = $edge['vendor'];
            $product['product_type'] = $edge['productType'];
            $product['created_at'] = $edge['createdAt'];
            $product['handle'] = $edge['handle'];
            $product['updated_at'] = $edge['updatedAt'];
            $product['published_at'] = $edge['publishedAt'];
            $product['template_suffix'] = $edge['templateSuffix'];
            $product['tags'] = implode(',', $edge['tags']);
            $product['status'] = $edge['status'];

            $product['variants'] = array();
            $product['options'] = array();
            $product['images'] = array();
            $product['image'] = array();

            foreach ($edge['variants']['edges'] as $key => $value) {

                $variant = array();
                $variant['id'] = str_replace('gid://shopify/ProductVariant/', '', $value['node']['id']);
                $variant['product_id'] = $product['id'];
                $variant['title'] = $value['node']['title'];
                $variant['price'] = $value['node']['price'];
                $variant['position'] = $value['node']['position'];
                $variant['inventory_policy'] = $value['node']['inventoryPolicy'];
                $variant['compare_at_price'] = $value['node']['compareAtPrice'];
                $variant['option1'] = $value['node']['selectedOptions'][0]['value'];
                $variant['option2'] = $value['node']['selectedOptions'][1]['value'] ?? null;
                $variant['option3'] = $value['node']['selectedOptions'][2]['value'] ?? null;
                $variant['created_at'] = $value['node']['createdAt'];
                $variant['updated_at'] = $value['node']['updatedAt'];
                $variant['taxable'] = $value['node']['taxable'];
                $variant['barcode'] = $value['node']['barcode'];
                if ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'KILOGRAMS') {
                    $variant['grams'] = round($value['node']['inventoryItem']['measurement']['weight']['value'] * 1000);
                    $variant['weight_unit'] = "kg";
                } elseif ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'GRAMS') {
                    $variant['grams'] = $value['node']['inventoryItem']['measurement']['weight']['value'];
                    $variant['weight_unit'] = "g";
                } elseif ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'OUNCES') {
                    $variant['grams'] = round($value['node']['inventoryItem']['measurement']['weight']['value'] * 28.3495);
                    $variant['weight_unit'] = "oz";
                } elseif ($value['node']['inventoryItem']['measurement']['weight']['unit'] == 'POUNDS') {
                    $variant['grams'] = round($value['node']['inventoryItem']['measurement']['weight']['value'] * 453.592);
                    $variant['weight_unit'] = "lb";
                }
                $variant['inventory_management'] = $value['node']['inventoryItem']['tracked'] == 'true' ? 'shopify' : 'not managed';
                $variant['requires_shipping'] = $value['node']['inventoryItem']['requiresShipping'];
                $variant['sku'] = $value['node']['sku'];
                $variant['weight'] = $value['node']['inventoryItem']['measurement']['weight']['value'];
                $variant['inventory_item_id'] = str_replace('gid://shopify/InventoryItem/', '', $value['node']['inventoryItem']['id']);
                $variant['inventory_quantity'] = $value['node']['inventoryQuantity'];
                $variant['old_inventory_quantity'] = $value['node']['inventoryQuantity'];
                $variant['image_id'] = isset($value['node']['image']) ? str_replace('gid://shopify/ProductImage/', '', $value['node']['image']['id']) : null;

                $product['variants'][] = $variant;

            }

            foreach ($edge['options'] as $key => $value) {

                $option = array();
                $option['id'] = str_replace('gid://shopify/ProductOption/', '', $value['id']);
                $option['product_id'] = $product['id'];
                $option['name'] = $value['name'];
                $option['position'] = $value['position'];
                $option['values'] = $value['values'];

                $product['options'][] = $option;

            }

            foreach ($edge['media']['edges'] as $key => $value) {

                if (isset($value['node']['image'])) {

                    $image = array();
                    $image['id'] = str_replace('gid://shopify/ImageSource/', '', $value['node']['image']['id']);
                    $image['product_id'] = $product['id'];
                    $image['alt'] = $value['node']['image']['altText'];
                    $image['width'] = $value['node']['image']['width'];
                    $image['height'] = $value['node']['image']['height'];
                    $image['src'] = $value['node']['image']['originalSrc'];

                    $product['images'][] = $image;

                }

            }

            if (!empty($product['images'])) {
                $product['image'][] = $product['images'][0];
            }

            return $product;

        }
    }

    /** 
     * To get Product Variant Count use this function.
     */
    public function productVariantsCount($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/productVariantsCount?example=Receive+a+count+of+all+Product+Variants
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/product-variant#get-products-product-id-variants-count
        */

        $limit = isset($params['limit']) ? $params['limit'] : 'null';

        $countQuery = <<<GRAPHQL
            query ProductVariantsCount(limit: $limit) {
                productVariantsCount {
                    count
                    precision
                }
            }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($countQuery);

        if (isset($responseData['data']['productVariantsCount']['userErrors']) && !empty($responseData['data']['products']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['products']['userErrors']);

        } else {

            return $responseData['data']['productVariantsCount'];

        }
    }

    public function deleteAllProductImages($productId)
    {


        if (strpos($productId, 'gid://shopify/Product') !== true) {
            $productId = "gid://shopify/Product/{$productId}";
        }
        $productquery = <<<GRAPHQL
                            query GetProductImages {
                                product(id: "{$productId}") {
                                    media(query: "media_type:IMAGE",first: 100) {
                                        nodes {
                                            id
                                            alt
                                            ... on MediaImage {
                                                createdAt
                                                image {
                                                    url
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($productquery);



        $imageIds = [];

        if (isset($responseData["data"]["product"]["media"]["nodes"])) {
            foreach ($responseData["data"]["product"]["media"]["nodes"] as $node) {
                if (isset($node["id"])) {
                    $imageIds[] = $node["id"];
                }
            }
        }

        $variables = [
            "mediaIds" => $imageIds,
            "productId" => $productId,
        ];




        $meidaquery = <<<'GRAPHQL'
                           mutation productDeleteMedia($mediaIds: [ID!]!, $productId: ID!) {
                                productDeleteMedia(mediaIds: $mediaIds, productId: $productId) {
                                    deletedMediaIds
                                    deletedProductImageIds
                                    mediaUserErrors {
                                        field
                                        message
                                    }
                                    product {
                                        id
                                        title
                                        media(first: 5) {
                                            nodes {
                                            alt
                                            mediaContentType
                                            status
                                            }
                                        }
                                    }
                                }
                            }
                        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($meidaquery, $variables);



        if (isset($responseData['data']['productDeleteMedia']['mediaUserErrors']) && !empty($responseData['data']['productDeleteMedia']['mediaUserErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productDeleteMedia']['mediaUserErrors']);

        }

        return $responseData;


    }

}
