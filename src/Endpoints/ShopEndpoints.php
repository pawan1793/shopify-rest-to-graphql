<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class ShopEndpoints
{

    const PlanNames = ["affiliate" => "Development", "staff" => "Staff", "cancelled" => "Cancelled", "staff_business" => "Staff Business", "trial" => "Trial", "dormant" => "Dormant", "frozen" => "Frozen", "singtel_trial" => "Singtel Trial", "partner_test" => "Developer Preview", "basic" => "Basic", "npo_lite" => "Npo Lite", "npo_full" => "Npo Full", "singtel_basic" => "Singtel Basic", "singtel_starter" => "Singtel Starter", "uafrica_basic" => "Uafrica Basic", "fraudulent" => "Fraudulent", "starter" => "Starter", "comped" => "Comped", "shopify_alumni" => "Shopify Alumni", "professional" => "Shopify", "custom" => "Custom", "unlimited" => "Advanced", "singtel_unlimited" => "Singtel Unlimited", "singtel_professional" => "Singtel Professional", "business" => "Business", "uafrica_professional" => "Uafrica Professional", "shopify_plus" => "Shopify Plus", "enterprise" => "Enterprise", "shopify_plus_trial" => "Plus Trial"];

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
    public function shopInfo($param = [])
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/shop
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/shop
        */

        global $graphqlService;

        $productTypeFields = '';
        if (!empty($param['fields'])) {
            $productTypeFields = implode("\n", array_map(fn($field) => $field, $param['fields']));
        }

        $productTypeQuery = '';
        if (!empty($productTypeFields)) {
          $productTypeQuery =
            'productTypes(first: 250) {
                edges {
                    node
                }
            }';
        } else {
            $productTypeQuery = '';
        }

        $shopquery = "
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
                orderNumberFormatPrefix
                orderNumberFormatSuffix
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
                $productTypeQuery
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
        }";

        $responseData = $this->graphqlService->graphqlQueryThalia($shopquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData = $responseData['data']['shop'];
            $responseData['id'] = str_replace('gid://shopify/Shop/', '', $responseData['id']);
            $responseData['plan_name'] = array_flip(self::PlanNames)[$responseData['plan']['displayName']] ?? '';
            $responseData['myshopify_domain'] = $responseData['myshopifyDomain'];
            $responseData['domain'] = $responseData['myshopifyDomain'];
            $responseData['shop_owner'] = $responseData['name'];
            $responseData['iana_timezone'] = $responseData['ianaTimezone'];
            $responseData['currency'] = $responseData['currencyCode'];

        }

        return $responseData;
    }
    
}