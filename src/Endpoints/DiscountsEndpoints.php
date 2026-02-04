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
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/pricerule#get-price-rules
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
                        cursor
                        node {
                            id
                            discount {
                                __typename
                                ... on DiscountAutomaticBasic {
                                    title
                                    customerGets {
                                        items {
                                            ... on DiscountProducts {
                                                products(first: 10) {
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
                                ... on DiscountCodeBasic {
                                    title
                                    customerGets {
                                        items {
                                            ... on DiscountProducts {
                                                products(first: 10) {
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
                                ... on DiscountCodeBxgy {
                                    title
                                    customerGets {
                                        items {
                                            ... on DiscountProducts {
                                                products(first: 10) {
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
                                ... on DiscountAutomaticBxgy {
                                    title
                                    customerGets {
                                        items {
                                            ... on DiscountProducts {
                                                products(first: 10) {
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
                foreach ($responseData['data']['discountNodes']['edges'] as $key => $edge) {
                    $node = $edge['node'];

                    $discountData = [
                        'id' => $node['id'],
                        'title' => $node['discount']['title'] ?? null,
                        'type' => $node['discount']['__typename'] ?? null,
                        'entitled_products' => [],
                        'cursor' => $edge['cursor'],
                    ];

                    // Get entitled products
                    if (isset($node['discount']['customerGets']['items']['products']['edges'])) {
                        foreach ($node['discount']['customerGets']['items']['products']['edges'] as $productEdge) {
                            $discountData['entitled_products'][] = str_replace('gid://shopify/Product/', '', $productEdge['node']['id']);
                        }
                    }

                    $discounts[$key] = $discountData;
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
