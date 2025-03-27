<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class InventoryEndpoints
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
     * To get Inventory Items use this function.
     */
    public function getInventoryItems($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/inventoryItems
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/inventorylevel#get-inventory-levels
        */



        $query = '';
        if (!empty($params['inventory_item_id'])) {
           
            if (strpos($params['inventory_item_id'], 'gid://shopify/InventoryItem') !== true) {
                $inventoryItemId = str_replace('gid://shopify/InventoryItem/', '', $params['inventory_item_id']);
            }
            $query = 'query: "id:' . $inventoryItemId . '"';
        }

        $inventoryitemsquery = <<<"GRAPHQL"
        query {
            inventoryItems(first: 250, $query) {
                edges {
                    node {
                        id
                        tracked
                        sku
                        requiresShipping
                        inventoryLevels(first: 250) {
                            edges {
                                node {
                                    id
                                    quantities (names: ["available"]) {
                                        quantity
                                    },
                                    location {
                                        id,
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($inventoryitemsquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData = $responseData['data']['inventoryItems']['edges'];

        }

        $finalarray = array();

        foreach ($responseData as $key => $inventoryItem) {

            $response = array();
            $locations = $inventoryItem['node']['inventoryLevels']['edges'];

            foreach ($locations as $lkey => $location) {

                $response['inventory_item_id'] = str_replace('gid://shopify/InventoryItem/', '', $inventoryItem['node']['id']);
                $response['tracked'] = $inventoryItem['node']['tracked'];
                $response['sku'] = $inventoryItem['node']['sku'];
                $response['requires_shipping'] = $inventoryItem['node']['requiresShipping'];
                $response['location_id'] = str_replace('gid://shopify/Location/', '', $location['node']['location']['id']);
                $response['location_name'] = $location['node']['location']['name'];
                $response['available'] = $location['node']['quantities'][0]['quantity'];

                $finalarray[$lkey] = $response;

            }

        }

        return $finalarray;
    }

    /** 
     * To get Inventory Item use this function.
     */
    public function getInventoryItem($inventoryItemId)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/inventoryItem
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/inventoryitem#get-inventory-items-inventory-item-id
        */



        if (strpos($inventoryItemId, 'gid://shopify/InventoryItem') !== true) {
            $inventoryItemId = str_replace('gid://shopify/InventoryItem/', '', $inventoryItemId);
        }

        $inventoryitemquery = <<<"GRAPHQL"
        query inventoryItem {
            inventoryItem(id: "gid://shopify/InventoryItem/{$inventoryItemId}") {
                id
                tracked
                sku
                requiresShipping
                countryCodeOfOrigin
                provinceCodeOfOrigin
                harmonizedSystemCode
                unitCost {
                    amount
                }
                countryHarmonizedSystemCodes (first: 100) {
                    edges {
                        node {
                            countryCode
                            harmonizedSystemCode
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($inventoryitemquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData = $responseData['data']['inventoryItem'];

        }

        $response = array();
        $response['id'] = str_replace('gid://shopify/InventoryItem/', '', $responseData['id']);
        $response['sku'] = $responseData['sku'];
        $response['requires_shipping'] = $responseData['requiresShipping'];
        $response['cost'] = $responseData['unitCost']['amount'] ?? 0;
        $response['country_code_of_origin'] = $responseData['countryCodeOfOrigin'];
        $response['province_code_of_origin'] = $responseData['provinceCodeOfOrigin'];
        $response['harmonized_system_code'] = $responseData['harmonizedSystemCode'];
        $response['tracked'] = $responseData['tracked'];
        $response['country_harmonized_system_codes'] = $responseData['countryHarmonizedSystemCodes']['edges'];

        return $response;
    }


    /** 
     * To Set Inventory Quantities use this function.
     */
    public function inventorySetQuantities($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/inventorySetQuantities?language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/inventorylevel#post-inventory-levels-adjust
        */



        $inventoryadjustvariables = array();

     

        if (!empty($params['location_id'])) {
            $inventoryadjustvariables['input']['reason'] = 'correction';
            $inventoryadjustvariables['input']['name'] = 'available';
            $inventoryadjustvariables['input']['ignoreCompareQuantity'] = true;
            $inventoryadjustvariables['input']['quantities'] = array();
            $inventoryadjustvariables['input']['quantities'][0]['quantity'] = $params['available'];
            $inventoryadjustvariables['input']['quantities'][0]['inventoryItemId'] = "gid://shopify/InventoryItem/{$params['inventory_item_id']}";
            $inventoryadjustvariables['input']['quantities'][0]['locationId'] = "gid://shopify/Location/{$params['location_id']}";
        }
       

        $inventoryadjustquery = <<<'GRAPHQL'
                mutation InventorySet($input: InventorySetQuantitiesInput!) {
                inventorySetQuantities(input: $input) {
                inventoryAdjustmentGroup {
                    createdAt
                    reason
                    referenceDocumentUri
                    changes {
                    name
                    delta
                    }
                }
                userErrors {
                    field
                    message
                }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($inventoryadjustquery, $inventoryadjustvariables);
        
        if (isset($responseData['data']['inventorySetQuantities']['userErrors']) && !empty($responseData['data']['inventorySetQuantities']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['inventorySetQuantities']['userErrors']);
        } else {
            $responseData = $responseData['data']['inventorySetQuantities'];
        }

        return $responseData;
    }


    /** 
     * To adjust Inventory Quantities use this function. (Don't Use this)
     */
    public function inventoryAdjustQuantities($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/inventoryAdjustQuantities
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/inventorylevel#post-inventory-levels-adjust
        */



        $inventoryadjustvariables = array();

        if (!empty($params['location_id'])) {
            $inventoryadjustvariables['input']['reason'] = 'correction';
            $inventoryadjustvariables['input']['name'] = 'available';
            $inventoryadjustvariables['input']['changes'] = array();
            $inventoryadjustvariables['input']['changes']['delta'] = $params['available_adjustment'];
            $inventoryadjustvariables['input']['changes']['inventoryItemId'] = "gid://shopify/InventoryItem/{$params['inventory_item_id']}";
            $inventoryadjustvariables['input']['changes']['locationId'] = "gid://shopify/Location/{$params['location_id']}";
        }

        $inventoryadjustquery = <<<'GRAPHQL'
            mutation inventoryAdjustQuantities($input: InventoryAdjustQuantitiesInput!) {
                inventoryAdjustQuantities(input: $input) {
                    userErrors {
                        field
                        message
                    }
                    inventoryAdjustmentGroup {
                        createdAt
                        reason
                        changes {
                            name
                            delta
                        }
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($inventoryadjustquery, $inventoryadjustvariables);

        if (isset($responseData['data']['inventoryAdjustQuantities']['userErrors']) && !empty($responseData['data']['inventoryAdjustQuantities']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['inventoryAdjustQuantities']['userErrors']);
        } else {

            $responseData = $responseData['data']['inventoryAdjustQuantities'];

        }

        return $responseData;
    }

}
