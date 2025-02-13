<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class LocationsEndpoints
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

    public function getLocations()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/locations
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/location#get-locations
        */



        $locationquery = <<<'GRAPHQL'
            query {
                locations(first: 250) {
                    edges {
                        node {
                            id
                            name
                            isActive
                        }
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($locationquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $responseData = $responseData['data']['locations']['edges'];

        }

        $response = array();

        foreach ($responseData as $key => $locations) {

            $response[$key]['id'] = str_replace('gid://shopify/Location/', '', $locations['node']['id']);
            $response[$key]['name'] = $locations['node']['name'];
            $response[$key]['active'] = $locations['node']['isActive'];

        }

        return $response;
    }
}
