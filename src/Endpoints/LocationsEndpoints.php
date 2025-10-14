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
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/locations
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/location#get-locations
        */



        $locationquery = <<<'GRAPHQL'
            query {
                locations(first: 250, includeLegacy: true) {
                    edges {
                        node {
                            id
                            name
                            isActive
                            isFulfillmentService
                        }
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($locationquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData = $responseData['data']['locations']['edges'];

        }

        $response = array();

        foreach ($responseData as $key => $locations) {

            $response[$key]['id'] = str_replace('gid://shopify/Location/', '', $locations['node']['id']);
            $response[$key]['name'] = $locations['node']['name'];
            $response[$key]['active'] = $locations['node']['isActive'];
            $response[$key]['fulfillmentservice'] = $locations['node']['isFulfillmentService'];

        }

        return $response;
    }
}
