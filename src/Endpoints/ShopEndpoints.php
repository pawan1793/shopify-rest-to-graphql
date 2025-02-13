<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;

class ShopEndpoints
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
     * To get Shop Info use this function.
     */
    public function shopInfo()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/shop
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/shop
        */



        $shopquery = <<<'GRAPHQL'
            query ShopShow {
                shop {
                alerts {
                    action {
                    title
                    url
                    }
                    description
                }
                billingAddress {
                    address1
                    address2
                    city
                    company
                    country
                    countryCodeV2
                    latitude
                    longitude
                    phone
                    province
                    provinceCode
                    zip
                }
                checkoutApiSupported
                contactEmail
                createdAt
                currencyCode
                currencyFormats {
                    moneyFormat
                    moneyInEmailsFormat
                    moneyWithCurrencyFormat
                    moneyWithCurrencyInEmailsFormat
                }
                customerAccounts
                description
                email
                enabledPresentmentCurrencies
                fulfillmentServices {
                    handle
                    serviceName
                }
                ianaTimezone
                id
                marketingSmsConsentEnabledAtCheckout
                myshopifyDomain
                name
                paymentSettings {
                    supportedDigitalWallets
                }
                plan {
                    displayName
                    partnerDevelopment
                    shopifyPlus
                }
                primaryDomain {
                    host
                    id
                }
                productTypes(first: 250) {
                    edges {
                    node
                    }
                }
                setupRequired
                shipsToCountries
                taxesIncluded
                taxShipping
                timezoneAbbreviation
                transactionalSmsDisabled
                updatedAt
                url
                weightUnit
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($shopquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $responseData = $responseData['data']['shop'];
            $responseData['id'] = str_replace('gid://shopify/Shop/', '', $responseData['id']);
            $responseData['plan_name'] = $responseData['plan']['displayName'];
            $responseData['myshopify_domain'] = $responseData['myshopifyDomain'];
            $responseData['shop_owner'] = $responseData['name'];
            $responseData['iana_timezone'] = $responseData['ianaTimezone'];

        }

        return $responseData;
    }


}
