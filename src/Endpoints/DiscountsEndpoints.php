<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlException;
use Thalia\ShopifyRestToGraphql\GraphqlService;

class DiscountsEndpoints
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
     * Get users all created discounts.
    */
    function discountNodes($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/queries/discountNodes?example=Retrieve+a+list+of+discounts
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/pricerule#get-price-rules
        */

        $position = 'first';
        $cursorparam = '';
        $limit = 250;

        if(isset($param['limit'])){
            $limit = $param['limit'];
        }

        if(isset($param['cursor'])){
            if(isset($param['direction']) && $param['direction'] == 'next'){
                $cursorparam = "after: \"{$param['cursor']}\"";
            }

            if(isset($param['direction']) && $param['direction'] == 'prev'){
                $position = 'last';
                $cursorparam = "before: \"{$param['cursor']}\"";
            }
        }

        $discountNodesQuery = <<<QUERY
            query {
                discountNodes($position: $limit, $cursorparam) {
                    edges {
                        node {
                            id
                            discount {
                                __typename
                                ... on DiscountAutomaticApp {
                                    title
                                    startsAt
                                    endsAt
                                }
                                ... on DiscountCodeBasic {
                                    title
                                    startsAt
                                    endsAt
                                    codes(first: 5) {
                                        edges {
                                            node {
                                            code
                                            }
                                        }
                                    }
                                    customerSelection {
                                        ... on DiscountCustomerAll {
                                            allCustomers
                                        }
                                    }
                                    customerGets {
                                        value {
                                            ... on DiscountPercentage {
                                                percentage
                                            }
                                            ... on DiscountAmount {
                                                amount {
                                                    amount
                                                    currencyCode
                                                }
                                            }
                                        }
                                        items {
                                            ... on DiscountProducts {
                                                products(first: 10) {
                                                    edges {
                                                        node {
                                                            id
                                                        }
                                                    }
                                                }
                                                productVariants(first: 10) {
                                                    edges {
                                                        node {
                                                            id
                                                        }
                                                    }
                                                }
                                            }
                                            ... on DiscountCollections {
                                                collections(first: 10) {
                                                    edges {
                                                        node {
                                                            id
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                    }
                }
            }
        QUERY;

        $responseData = $this->graphqlService->graphqlQueryThalia($discountNodesQuery);

        if(isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $discounts = [];

            if (isset($responseData['data']['discountNodes']['edges'])) {
                foreach ($responseData['data']['discountNodes']['edges'] as $edge) {
                    $node = $edge['node'];
                    $discountData = [
                        'id' => $node['id'],
                        'title' => $node['discount']['title'] ?? null,
                        'type' => $node['discount']['__typename'] ?? null,
                        'starts_at' => $node['discount']['startsAt'] ?? null,
                        'ends_at' => $node['discount']['endsAt'] ?? null,
                        'codes' => [],
                        'value' => null,
                        'value_type' => null,
                        'entitled_products' => [],
                        'entitled_variants' => [],
                        'entitled_collections' => [],
                    ];

                    // Get discount codes if available
                    if (isset($node['discount']['codes']['edges'])) {
                        foreach ($node['discount']['codes']['edges'] as $codeEdge) {
                            $discountData['codes'][] = $codeEdge['node']['code'];
                        }
                    }

                    // Get discount value (fixed amount or percentage)
                    if (isset($node['discount']['customerGets']['value'])) {
                        if (isset($node['discount']['customerGets']['value']['percentage'])) {
                            $discountData['value'] = $node['discount']['customerGets']['value']['percentage'];
                            $discountData['value_type'] = 'percentage';
                        } elseif (isset($node['discount']['customerGets']['value']['amount']['amount'])) {
                            $discountData['value'] = $node['discount']['customerGets']['value']['amount']['amount'];
                            $discountData['value_type'] = 'fixed_amount';
                        }
                    }

                    // Get entitled products
                    if (isset($node['discount']['customerGets']['items']['products']['edges'])) {
                        foreach ($node['discount']['customerGets']['items']['products']['edges'] as $productEdge) {
                            $discountData['entitled_products'][] = $productEdge['node']['id'];
                        }
                    }

                    // Get entitled variants
                    if (isset($node['discount']['customerGets']['items']['productVariants']['edges'])) {
                        foreach ($node['discount']['customerGets']['items']['productVariants']['edges'] as $variantEdge) {
                            $discountData['entitled_variants'][] = $variantEdge['node']['id'];
                        }
                    }

                    // Get entitled collections
                    if (isset($node['discount']['customerGets']['items']['collections']['edges'])) {
                        foreach ($node['discount']['customerGets']['items']['collections']['edges'] as $collectionEdge) {
                            $discountData['entitled_collections'][] = $collectionEdge['node']['id'];
                        }
                    }

                    $discounts[] = $discountData;
                }

                $pageInfo = $responseData['data']['discountNodes']['pageInfo'];

                $responseData = [
                    'data' => $discounts,
                    'pageInfo' => $pageInfo,
                ];
            }

            return $responseData;
        }
    }
}
