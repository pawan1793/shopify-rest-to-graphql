<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class OauthScopeEndpoints
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

    public function currentAppInstallation()
    {

        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/currentAppInstallation
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/accessscope#get-admin-oauth-access-scopes
        */



        $accessscopequery = <<<'GRAPHQL'
            query {
                currentAppInstallation {
                    accessScopes {
                    handle
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($accessscopequery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {
            $handles = [];
            foreach ($responseData['data']['currentAppInstallation']['accessScopes'] as $key => $response) {
                $handles[$key]['handle'] = $response['handle'];
            }
            $responseData = $handles;
            // $responseData = array_map(function ($item) {
            //     return $item['handle'];
            // }, $responseData);
        }

        return $responseData;
    }

}
