<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class ShippingEndpoints
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
     * To get Delivery Profiles use this function.
     */
    public function getDeliveryProfiles()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/deliveryProfiles
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/shippingzone
        */



        $deliveryprofilequery = <<<"GRAPHQL"
    query DeliveryZoneList {
        deliveryProfiles(first: 10) {
            edges {
                node {
                    id
                        profileLocationGroups {
                            locationGroup {
                                id
                            }
                            locationGroupZones(first: 10) {
                                edges {
                                    node {
                                        zone {
                                            id
                                            name
                                            countries {
                                                code {
                                                    countryCode
                                                    restOfWorld
                                                }
                                                provinces {
                                                    name
                                                    code
                                                }
                                            }
                                        }
                                        methodDefinitions(first: 10) {
                                            edges {
                                                node {
                                                    id
                                                    active
                                                    description
                                                    methodConditions {
                                                    field
                                                    operator
                                                    conditionCriteria {
                                                        __typename
                                                        ... on MoneyV2 {
                                                        amount
                                                        currencyCode
                                                        }
                                                        ... on Weight {
                                                        unit
                                                        value
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
                }
            }
        }
    }
    GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($deliveryprofilequery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData = $responseData['data']['deliveryProfiles'];

        }

        return $responseData;
    }
}
