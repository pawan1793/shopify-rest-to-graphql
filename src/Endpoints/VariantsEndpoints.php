<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class VariantsEndpoints
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
     * To update Bulk Product Variants use this function.
     */
    public function productVariantsBulkUpdate($shopifyId, $variantId, $params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/productVariantsBulkUpdate
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-07/resources/product-variant#put-variants-variant-id
        */




        $variant = $params['variant'];


        $productId = $shopifyId;
        if (strpos($shopifyId, 'gid://shopify/Product') !== true) {
            $productId = "gid://shopify/Product/{$shopifyId}";
        }


        if (strpos($variantId, 'gid://shopify/ProductVariant') !== true) {
            $variantId = "gid://shopify/ProductVariant/{$variantId}";
        }


        $finalvariantvariables['variants'][] = ['id' => $variantId];


        $variantdata['id'] = $variantId;

        if (!empty($variant['price'])) {
            $variantdata['price'] = $variant['price'];
        }

        if (!empty($variant['compare_at_price'])) {
            $variantdata['compareAtPrice'] = $variant['compare_at_price'];
        }

        if (!empty($variant['barcode'])) {
            $variantdata['barcode'] = $variant['barcode'];
        }


        if (!empty($variant['taxable'])) {
            $variantdata['taxable'] = isset($variant['taxable']) ? $variant['taxable'] : true;
        }

        if (!empty($variant['mediaId'])) {
            $variantdata['mediaId'] = $variant['mediaId'];
        }

        if (!empty($variant['mediaSrc'])) {
            $variantdata['mediaSrc'] = $variant['mediaSrc'];
        }



        // if (!empty($variant['optionValues'])) {
        //     $variantdata['optionValues']['id'] = $variant['optionValues']['optionId'] ;
        //     $variantdata['optionValues']['optionName'] = $variant['optionValues']['name'] ;
        // }

        // // if (!empty($variant['optionValues'])) {
        // //     $variantdata['optionValues']['linkedMetafieldValue'] = $variant['optionValues_linkedMetafieldValue'] ;
        // // }

        // // if (!empty($variant['optionValues'])) {
        // //     $variantdata['optionValues']['optionId'] = $variant['optionValues_optionId'] ;
        // // }

        if (!empty($variant['metafields'])) {
            if (strpos($variant['metafields'][0]['id'], 'gid://shopify/Metafield') !== true) {
                $metafieldId = "gid://shopify/Metafield/{$variant['metafields'][0]['id']}";
            }
        }

        if (!empty($metafieldId)) {
            $variantdata['metafields']['id'] = $metafieldId;
            $variantdata['metafields']['value'] = $variant['metafields'][1]['value'];
        }


        if (!empty($variant['cost'])) {
            $variantdata['inventoryItem']['cost'] = $variant['cost'];
            $variantdata['inventoryItem']['tracked'] = true;
        }

        if (!empty($variant['countryCodeOfOrigin'])) {
            $variantdata['inventoryItem']['countryCodeOfOrigin'] = $variant['countryCodeOfOrigin'];
        }

        if (!empty($variant['taxCode'])) {
            $variantdata['taxCode'] = $variant['taxCode'];
        }

        if (!empty($variant['sku'])) {
            $variantdata['inventoryItem']['sku'] = $variant['sku'];
        }

        if (!empty($variant['inventoryPolicy'])) {
            $variantdata['inventoryPolicy'] = $variant['inventoryPolicy'];
        }

        if (!empty($variant['inventoryQuantities'])) {
            $variantdata['inventoryQuantities']['availableQuantity'] = $variant['availableQuantity'];
        }

        if (!empty($variant['inventoryQuantities'])) {
            $variantdata['inventoryQuantities']['locationId'] = $variant['locationId'];
        }

        if (!empty($variant['requiresComponents'])) {
            $variantdata['requiresComponents'] = $variant['requiresComponents'];
        }



        if (isset($variant['weight'])) {

            $variantdata['inventoryItem']['measurement']['weight']['value'] = (float) $variant['weight'];
            if (isset($variant['weight_unit'])) {
                switch ($variant['weight_unit']) {
                    case 'lb':
                        $weight_unit = 'POUNDS';
                        break;

                    case 'g':
                        $weight_unit = 'GRAMS';
                        break;
                    case 'oz':
                        $weight_unit = 'OUNCES';
                        break;

                    case 'kg':
                        $weight_unit = 'KILOGRAMS';
                        break;

                    default:
                        $weight_unit = 'KILOGRAMS';
                        break;
                }

                $variantdata['inventoryItem']['measurement']['weight']['unit'] = $weight_unit;
            }

        }


        $finalvariantvariables['productId'] = $productId;
        $finalvariantvariables['variants'][] = $variantdata;



        $variantquery = <<<'GRAPHQL'
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
            productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                product {
                    id
                }
                productVariants {
                    id
                    title
                    inventoryItem{
                        id
                    }
                    metafields(first: 2) {
                        edges {
                            node {
                            namespace
                            key
                            value
                            }
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
            }
            GRAPHQL;



        $responseData = $this->graphqlService->graphqlQueryThalia($variantquery, $finalvariantvariables);
        print_r($responseData);

        if (isset($responseData['data']['productVariantsBulkUpdate']['userErrors']) && !empty($responseData['data']['productVariantsBulkUpdate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariantsBulkUpdate']['userErrors']);

        } else {
            $responseData = $responseData['data']['productVariantsBulkUpdate'];
        }


        return $responseData;
    }

    /** 
     * To Delete Bulk Product Variants use this function.
     */
    public function productVariantsBulkDelete($shopifyId, $variantId)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/productVariantsBulkDelete
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/product-variant#delete-products-product-id-variants-variant-id
        */




        $productId = $shopifyId;
        if (strpos($shopifyId, 'gid://shopify/Product') !== true) {
            $productId = "gid://shopify/Product/{$shopifyId}";
        }



        if (strpos($variantId, 'gid://shopify/ProductVariant') !== true) {
            $variantId = "gid://shopify/ProductVariant/{$variantId}";
        }


        $variantdata['id'] = $variantId;

        $finalvariantvariables = [
            'productId' => $productId,
            'variantsIds' => [$variantId]
        ];


        $variantquery = <<<'GRAPHQL'
            mutation bulkDeleteProductVariants($productId: ID!, $variantsIds: [ID!]!) {
                productVariantsBulkDelete(productId: $productId, variantsIds: $variantsIds) {
                    product {
                        id
                        title
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;



        $responseData = $this->graphqlService->graphqlQueryThalia($variantquery, $finalvariantvariables);


        if (isset($responseData['data']['productVariantsBulkDelete']['userErrors']) && !empty($responseData['data']['productVariantsBulkDelete']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariantsBulkDelete']['userErrors']);

        } else {
            $responseData = $responseData['data']['productVariantsBulkDelete'];
        }


        return $responseData;
    }

    public function getVariantById($id)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/productVariant
        */

        $gid = "gid://shopify/ProductVariant/{$id}";
        $query = <<<GRAPHQL
        query GetProductVariant(\$id: ID!) {
            productVariant(id: \$id) {
                id
                title
                availableForSale
                barcode
                compareAtPrice
                createdAt
                inventoryQuantity
                inventoryItem {
                    id
                }
            }
        }
        GRAPHQL;

        $variables = [
            'id' => $gid,
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query,$variables);

        if (isset($responseData['data']['productVariant']['userErrors']) && !empty($responseData['data']['productVariant']['userErrors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['products']['userErrors']);

        } else {

            return $responseData['data']['productVariant'];

        }
    }

    public function productVariantAppendMedia($id, array $variantMedias)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/mutations/productVariantAppendMedia
        */

        $productId = "gid://shopify/Product/{$id}";
        $variantMedia = [];
        foreach ($variantMedias as $media) {
            $variantMedia[] = [
                'mediaIds' => ['gid://shopify/MediaImage/' . $media['id']],
                'variantId' => "gid://shopify/ProductVariant/" . $media['variantId']
            ];
        }

        $query = <<<GRAPHQL
        mutation productVariantAppendMedia(
          \$productId: ID!
          \$variantMedia: [ProductVariantAppendMediaInput!]!
        ) {
          productVariantAppendMedia(
            productId: \$productId
            variantMedia: \$variantMedia
          ) {
            product {
              id
            }
            productVariants {
              id
              media(first: 5) {
                edges {
                  node {
                    __typename
                    ... on MediaImage {
                      id
                      preview {
                        image { url }
                      }
                    }
                  }
                }
              }
            }
            userErrors {
              field
              message
              code
            }
          }
        }
        GRAPHQL;

        $variables = [
            'productId' => $productId,
            'variantMedia' => $variantMedia
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['data']['productVariantAppendMedia']['userErrors']) && count($responseData['data']['productVariantAppendMedia']['userErrors']) > 0) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariantAppendMedia']['userErrors']);
        } else {
            return $responseData['data']['productVariantAppendMedia'];
        }
    }
}
