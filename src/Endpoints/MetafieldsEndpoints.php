<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class MetafieldsEndpoints
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

    // METAFIELD DELETE FUNCTION
    public function metafieldsDelete($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/metafieldsDelete
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-10/resources/metafield#delete-blogs-blog-id-metafields-metafield-id
        */



        $id = $params['metafields']['ownerId'];

        $namespace = $params['metafields']['namespace'];
        $key = $params['metafields']['key'];

        $finalmetafieldsvariables['metafields'] = [
            [
                'ownerId' => $id,
                'namespace' => $namespace,
                'key' => $key
            ]
        ];



        $metafieldquery = <<<'GRAPHQL'
            mutation MetafieldsDelete($metafields: [MetafieldIdentifierInput!]!) {
                metafieldsDelete(metafields: $metafields) {
                    deletedMetafields {
                        key
                        namespace
                        ownerId
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }

            GRAPHQL;



        $responseData = $this->graphqlService->graphqlQueryThalia($metafieldquery, $finalmetafieldsvariables);


        if (isset($responseData['data']['metafieldsDelete']['userErrors']) && !empty($responseData['data']['metafieldsDelete']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['metafieldsDelete']['userErrors']);

        } else {
            $responseData = $responseData['data']['metafieldsDelete']['deletedMetafields'];
        }


        return $responseData;
    }

    // METAFIELD CREATE FUNCTION
    public function metafieldsSet($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/metafieldsSet
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-10/resources/metafield#post-blogs-blog-id-metafields
        */



        $key = $params['metafield']['key'];
        $namespace = $params['metafield']['namespace'];

        $ownerId = $params['metafield']['ownerId'];
        $type = $params['metafield']['type'];
        $value = $params['metafield']['value'];

        $finalmetafieldsvariables['metafields'] = [
            [
                'key' => $key,
                'namespace' => $namespace,
                'ownerId' => $ownerId,
                'type' => $type,
                'value' => $value
            ]
        ];


        $metafieldquery = <<<'GRAPHQL'
            mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
                metafieldsSet(metafields: $metafields) {
                    metafields {
                        key
                        namespace
                        value
                        createdAt
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                        code
                    }
                }
            }
            GRAPHQL;



        $responseData = $this->graphqlService->graphqlQueryThalia($metafieldquery, $finalmetafieldsvariables);


        if (isset($responseData['data']['metafieldsSet']['userErrors']) && !empty($responseData['data']['metafieldsSet']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['metafieldsSet']['userErrors']);

        } else {

            $responseData = $responseData['data']['metafieldsSet']['metafields'];

        }


        return $responseData;
    }

    // METAFIELD GET FUNCTION
    public function getMetafields($params)
    {
        /*
            GraphQL Reference: https://shopify.dev/docs/api/admin-graphql/2025-01/queries/product?example=Get+metafields+attached+to+a+product
            Rest Reference: https://shopify.dev/docs/api/admin-rest/2025-01/resources/metafield#get-blogs-blog-id-metafields
        */



        if (isset($params['productid'])) {

            $id = "gid://shopify/Product/{$params['productid']}";

        } else if (isset($params['variantid'])) {

            $id = "gid://shopify/ProductVariant/{$params['variantid']}";

        }

        if (isset($params["namespace"]) && isset($params["key"])) {

            $namespace = $params["namespace"];
            $key = $params["key"];

        }

        if (isset($id)) {

            // TO GET ALL THE METAFIELDS OF A PRODUCT AND TO GET A SPECIFIC METAFIELD OF A PRODUCT
            if (strpos($id, 'gid://shopify/Product/') !== false) {

                if (!isset($namespace)) {

                    $variables = [
                        'ownerId' => $id
                    ];

                    $metafieldquery = <<<'GRAPHQL'
                        query ProductMetafields($ownerId: ID!) {
                            product(id: $ownerId) {
                                metafields(first: 250) {
                                    edges {
                                        node {
                                            id
                                            namespace
                                            key
                                            value
                                            type
                                        }
                                    }
                                }
                            }
                        }
                        GRAPHQL;

                    $responseData = $this->graphqlService->graphqlQueryThalia($metafieldquery, $variables);

                    if (isset($responseData['errors']) && !empty($responseData['errors'])) {

                        throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                    } else {

                        $productMetafieldsData = [];

                        foreach ($responseData['data']['product']['metafields']['edges'] as $response) {

                            $node = $response['node'];
                            $node['id'] = str_replace("gid://shopify/Metafield/", "", $node['id']);
                            $productMetafieldsData[] = $node;

                        }

                        return $productMetafieldsData;
                    }

                } else {

                    $variables = [
                        'ownerId' => $id,
                        'namespace' => $namespace,
                        'key' => $key
                    ];

                    $metafieldquery = <<<'GRAPHQL'
                        query ProductMetafield($ownerId: ID!, $namespace: String!, $key: String!) {
                            product(id: $ownerId) {
                                metafield(namespace: $namespace, key: $key) { 
                                    id
                                    namespace
                                    key
                                    value
                                    type
                                }
                            }
                        }
                        GRAPHQL;

                    $responseData = $this->graphqlService->graphqlQueryThalia($metafieldquery, $variables);

                    if (isset($responseData['errors']) && !empty($responseData['errors'])) {

                        throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                    } else {

                        $productMetafieldData = [];

                        if (!empty($responseData['data']['product']['metafield'])) {

                            $productMetafieldData = $responseData['data']['product']['metafield'];
                            $productMetafieldData['id'] = str_replace("gid://shopify/Metafield/", "", $responseData['id']);

                        }

                    }

                    return $productMetafieldData;
                }

            }
            // TO GET ALL THE METAFIELDS OF A PRODUCT VARIANT AND TO GET A SPECIFIC METAFIELD OF A PRODUCT VARIANT
            else if (strpos($id, 'gid://shopify/ProductVariant/') !== false) {

                if (!isset($namespace)) {

                    $variables = [
                        'ownerId' => $id
                    ];

                    $metafieldquery = <<<'GRAPHQL'
                        query ProductVariantMetafields($ownerId: ID!) {
                            productVariant(id: $ownerId) {
                                metafields(first: 250) {
                                    edges {
                                        node {
                                            id
                                            namespace
                                            key
                                            value
                                            type
                                        }
                                    }
                                }
                            }
                        }
                        GRAPHQL;

                    $responseData = $this->graphqlService->graphqlQueryThalia($metafieldquery, $variables);

                    if (isset($responseData["errors"]) && !empty($responseData["errors"])) {

                        throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                    } else {

                        $variantMetafieldsData = [];

                        foreach ($responseData['data']['productVariant']['metafields']['edges'] as $response) {

                            $node = $response['node'];
                            $node['id'] = str_replace("gid://shopify/Metafield/", "", $node['id']);
                            $variantMetafieldsData[] = $node;

                        }

                    }

                    return $variantMetafieldsData;

                } else {

                    $variables = [
                        'ownerId' => $id,
                        'namespace' => $namespace,
                        'key' => $key
                    ];

                    $metafieldquery = <<<'GRAPHQL'
                        query ProductVariantMetafield($ownerId: ID!, $namespace: String!, $key: String!) {
                            productVariant(id: $ownerId) {
                                metafield(namespace: $namespace, key: $key) { 
                                    id
                                    namespace
                                    key
                                    value
                                    type
                                }
                            }
                        }
                        GRAPHQL;

                    $responseData = $this->graphqlService->graphqlQueryThalia($metafieldquery, $variables);

                    if (isset($responseData['errors']) && !empty($responseData['errors'])) {

                        throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                    } else {

                        $variantMetafieldData = [];

                        if (!empty($responseData['data']['productVariant']['metafield'])) {

                            $variantMetafieldData = $responseData['data']['productVariant']['metafield'];
                            $variantMetafieldData['id'] = str_replace("gid://shopify/Metafield/", "", $responseData['id']);

                        }

                    }

                    return $variantMetafieldData;
                }
            }

        } else {

            // TO GET ALL THE METAFIELDS OF A SHOP AND TO GET A SPECIFIC METAFIELD OF A SHOP
            if (!isset($namespace)) {

                $metafieldQuery = <<<'GRAPHQL'
                    query getShopMetafields {
                        shop {
                            metafields(first: 250) {
                                edges {
                                    node {
                                        id
                                        namespace
                                        key
                                        value
                                        type
                                    }
                                }
                            }
                        }
                    }
                    GRAPHQL;

                $responseData = $this->graphqlService->graphqlQueryThalia($metafieldQuery, []);

                if (isset($responseData['errors']) && !empty($responseData['errors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } else {

                    $metafields = [];

                    foreach ($responseData['data']['shop']['metafields']['edges'] as $response) {

                        $node = $response['node'];
                        $node['id'] = str_replace("gid://shopify/Metafield/", "", $node['id']);
                        $metafields[] = $node;

                    }
                }

                $shopMetafieldsData = ['metafields' => $metafields];

                return $shopMetafieldsData;

            } else {

                $metafieldQuery = <<<'GRAPHQL'
                    query getShopMetafield($namespace: String!, $key: String!) {
                        shop {
                            metafield(namespace: $namespace, key: $key) {
                                id
                                namespace
                                key
                                value
                                type
                            }
                        }
                    }
                    GRAPHQL;

                $variables = [
                    'namespace' => $namespace,
                    'key' => $key
                ];

                $responseData = $this->graphqlService->graphqlQueryThalia($metafieldQuery, $variables);

                if (isset($responseData['errors']) && !empty($responseData['errors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } else {

                    $responseData = $responseData['data']['shop']['metafield'];
                    $responseData['id'] = str_replace("gid://shopify/Metafield/", "", $responseData['id']);

                }

                return $responseData;

            }
        }

    }


}
