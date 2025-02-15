<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
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

    public function getCustomCollections($params = array())
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/collections
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/customcollection#get-custom-collections
        */


        $position = 'first';
        $cursorparam = '';
        $limit = 250;

        if(isset($params['limit'])){
            $limit = $params['limit'];
        }

        if(isset($params['cursor'])){

            if(isset($params['direction']) && $params['direction'] == 'next'){
                $cursorparam = "after: \"{$params['cursor']}\"";
            }

            if(isset($params['direction']) && $params['direction'] == 'prev'){
                $position = 'last';
                $cursorparam = "before: \"{$params['cursor']}\"";
            }

        }

        $collectionsquery = <<<"GRAPHQL"
        query CustomCollectionList {
            collections($position: $limit, query: "collection_type:custom", $cursorparam) {
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
                edges {
                    cursor
                    node {
                        id
                        title
                    }
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($collectionsquery);

        if (isset($responseData['data']['errors']) && !empty($responseData['data']['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['errors']);

        } else {

            $collectionsResponseData = $responseData['data']['collections'];

        }

        $collectionsData = [
            'pageInfo' => $collectionsResponseData['pageInfo'],
            'collections' => array_map(function ($collection) {
                return [
                    'id' => str_replace('gid://shopify/Collection/', '', $collection['node']['id']),
                    'title' => $collection['node']['title'],
                    'cursor' => $collection['cursor'],
                ];
            }, $collectionsResponseData['edges'])
        ];

        return $collectionsData;
    }

    public function getSmartCollections($params = array())
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/collections
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/smartcollection#get-smart-collections
        */


        $position = 'first';
        $cursorparam = '';
        $limit = 250;

        if(isset($params['limit'])){
            $limit = $params['limit'];
        }

        if(isset($params['cursor'])){

            if(isset($params['direction']) && $params['direction'] == 'next'){
                $cursorparam = "after: \"{$params['cursor']}\"";
            }

            if(isset($params['direction']) && $params['direction'] == 'prev'){
                $position = 'last';
                $cursorparam = "before: \"{$params['cursor']}\"";
            }

        }

        $collectionsquery = <<<"GRAPHQL"
            query CustomCollectionList {
                collections($position: $limit, query: "collection_type:smart", $cursorparam) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                    }
                    edges {
                        cursor
                        node {
                            id
                            title
                        }
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($collectionsquery);

        if (isset($responseData['data']['errors']) && !empty($responseData['data']['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['errors']);

        } else {

            $collectionsResponseData = $responseData['data']['collections'];

        }

        $collectionsData = [
            'pageInfo' => $collectionsResponseData['pageInfo'],
            'collections' => array_map(function ($collection) {
                return [
                    'id' => str_replace('gid://shopify/Collection/', '', $collection['node']['id']),
                    'title' => $collection['node']['title'],
                    'cursor' => $collection['cursor'],
                ];
            }, $collectionsResponseData['edges'])
        ];

        return $collectionsData;
    }

    public function getCollection($collectionId)
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/collection
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/customcollection#get-custom-collections-custom-collection-id, https://shopify.dev/docs/api/admin-rest/2025-01/resources/smartcollection#get-smart-collections-smart-collection-id
        */



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

        $responseData = $this->graphqlService->graphqlQueryThalia($collectionquery, $collectionvariable);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData = $responseData['data']['collection'];
            $responseData['id'] = str_replace('gid://shopify/Collection/', '', $responseData['id']);

        }

        return $responseData;
    }
}
