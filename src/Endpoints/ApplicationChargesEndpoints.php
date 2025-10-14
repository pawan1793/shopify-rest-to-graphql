<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class ApplicationChargesEndpoints
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

    public function appPurchaseOneTimeCreate($params)
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/appPurchaseOneTimeCreate
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/applicationcharge#post-application-charges
        */



        $applicationcharge = $params['application_charge'];

        $chargevariables = array();

        if (!empty($applicationcharge['name'])) {
            $chargevariables['name'] = $applicationcharge['name'];
        }

        if (!empty($applicationcharge['price'])) {
            $chargevariables['price']['amount'] = $applicationcharge['price'];
            $chargevariables['price']['currencyCode'] = 'USD';
        }

        if (!empty($applicationcharge['return_url'])) {
            $chargevariables['returnUrl'] = $applicationcharge['return_url'];
        }

        if (!empty($applicationcharge['test'])) {
            $chargevariables['test'] = $applicationcharge['test'];
        }else{
            $chargevariables['test'] = false;
        }

        
       
        $applicationchargequery = <<<'GRAPHQL'
            mutation AppPurchaseOneTimeCreate($name: String!, $price: MoneyInput!, $returnUrl: URL!, $test: Boolean!) {
                appPurchaseOneTimeCreate(name: $name, returnUrl: $returnUrl, price: $price, test: $test) {
                    userErrors {
                        field
                        message
                    }
                    appPurchaseOneTime {
                        createdAt
                        id
                    }
                    confirmationUrl
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($applicationchargequery, $chargevariables);
        \Log::info($responseData);
        $response = array();

        if (isset($responseData['data']['appPurchaseOneTimeCreate']['userErrors']) && !empty($responseData['data']['appPurchaseOneTimeCreate']['userErrors'])) {

            throw new GraphqlException("Shopify API request failed", 400, $responseData['data']['appPurchaseOneTimeCreate']['userErrors']);

        } else {

            $response['confirmation_url'] = $responseData['data']['appPurchaseOneTimeCreate']['confirmationUrl'];

        }

        return $response;
    }

    public function currentAppInstallationForOneTime($chargeId)
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/currentAppInstallation?example=Retrieves+a+list+of+application+charges
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/applicationcharge#get-application-charges-application-charge-id
        */



        $getappinstallationquery = <<<'GRAPHQL'
            query {
                currentAppInstallation {
                    oneTimePurchases(first: 10, sortKey: CREATED_AT, reverse: true) {
                        edges {
                            node {
                                id
                                name
                                status
                                price {
                                    amount
                                    currencyCode
                                }
                                createdAt
                            }
                        }
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($getappinstallationquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException("Shopify API request failed", 400, $responseData['errors']);

        } else {

            $responseData = $responseData['data']['currentAppInstallation']['oneTimePurchases']['edges'];

        }

        $chargeId = "gid://shopify/AppPurchaseOneTime/{$chargeId}";
        $response = array();

        foreach ($responseData as $key => $charges) {

            if ($chargeId == $charges['node']['id']) {

                $response['status'] = 'active';
                $response['message'] = 'The charge is active';
                $response['price'] = $charges['node']['price']['amount'];
                $response['created_at'] = $charges['node']['createdAt'];
                break;

            } else {

                $response['status'] = strtolower($charges['node']['status']);
                $response['message'] = 'The charge is not active and is in different status';

            }

        }

        return $response;
    }

}