<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;

class CollectionsEndpoints
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

    public function getCustomCollections()
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/collections
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/customcollection#get-custom-collections
        */

        global $graphqlService;

        $collectionsquery = <<<'GRAPHQL'
                                query CustomCollectionList {
                                    collections(first: 250, query: "collection_type:custom") {
                                        nodes {
                                            id
                                            title
                                        }
                                    }
                                }
                                GRAPHQL;

        $responseData = $graphqlService->graphqlQueryThalia($collectionsquery);

        if (isset($responseData['data']['errors']) && !empty($responseData['data']['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['errors'], true));

        } else {

            $responseData = $responseData['data']['collections'];

        }

        return $responseData;
    }

    public function getSmartCollections()
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/collections
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/smartcollection#get-smart-collections
        */

        global $graphqlService;

        $collectionsquery = <<<'GRAPHQL'
                                query CustomCollectionList {
                                    collections(first: 250, query: "collection_type:smart") {
                                        nodes {
                                            id
                                            title
                                        }
                                    }
                                }
                                GRAPHQL;

        $responseData = $graphqlService->graphqlQueryThalia($collectionsquery);

        if (isset($responseData['data']['errors']) && !empty($responseData['data']['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['errors'], true));

        } else {

            $responseData = $responseData['data']['collections'];

        }

        return $responseData;
    }

    function getCollection($collectionId)
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/collection
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/customcollection#get-custom-collections-custom-collection-id, https://shopify.dev/docs/api/admin-rest/2025-01/resources/smartcollection#get-smart-collections-smart-collection-id
        */

        global $graphqlService;

        $collectionvariable = array();

        if (isset($collectionId)) {
            if (strpos($collectionId, "gid://shopify/Collection/") !== false) {
                $collectionId = str_replace("gid://shopify/Collection/", "", $collectionId);
            }
            $collectionvariable['collection'] = "gid://shopify/Collection/" . $collectionId;
        }

        $collectionquery = <<<'GRAPHQL'
                            query Collection($collection: ID!) {
                                collection(id: $collection) {
                                    id
                                    title
                                }
                            }
                            GRAPHQL;

        $responseData = $graphqlService->graphqlQueryThalia($collectionquery, $collectionvariable);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $responseData = $responseData['data']['collection'];

        }

        return $responseData;
    }
}
