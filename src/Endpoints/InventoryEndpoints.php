<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;

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

    public function getInventoryItems($params)
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/inventoryItems
        Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/inventorylevel#get-inventory-levels
        */



        $query = '';
        if (!empty($params['inventory_item_id'])) {
            if (strpos($params['inventory_item_id'], 'gid://shopify/InventoryItem') !== false) {
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

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $responseData = $responseData['data']['inventoryItems']['edges'];

        }

        $finalarray = array();

        foreach ($responseData as $key => $inventoryItem) {

            $response = array();
            $locations = $inventoryItem['node']['inventoryLevels']['edges'];

            foreach ($locations as $lkey => $location) {

                $response[$lkey]['inventory_item_id'] = str_replace('gid://shopify/InventoryItem/', '', $inventoryItem['node']['id']);
                $response[$lkey]['tracked'] = $inventoryItem['node']['tracked'];
                $response[$lkey]['sku'] = $inventoryItem['node']['sku'];
                $response[$lkey]['requires_shipping'] = $inventoryItem['node']['requiresShipping'];
                $response[$lkey]['location_id'] = str_replace('gid://shopify/Location/', '', $location['node']['location']['id']);
                $response[$lkey]['location_name'] = $location['node']['location']['name'];
                $response[$lkey]['available'] = $location['node']['quantities'][0]['quantity'];

            }

            array_push($finalarray, $response);

        }

        return $finalarray;
    }

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

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $responseData = $responseData['data']['inventoryItem'];

        }

        $response = array();
        $response['id'] = str_replace('gid://shopify/InventoryItem/', '', $responseData['id']);
        $response['sku'] = $responseData['sku'];
        $response['requires_shipping'] = $responseData['requiresShipping'];
        $response['cost'] = $responseData['unitCost']['amount'];
        $response['country_code_of_origin'] = $responseData['countryCodeOfOrigin'];
        $response['province_code_of_origin'] = $responseData['provinceCodeOfOrigin'];
        $response['harmonized_system_code'] = $responseData['harmonizedSystemCode'];
        $response['tracked'] = $responseData['tracked'];
        $response['country_harmonized_system_codes'] = $responseData['countryHarmonizedSystemCodes']['edges'];

        return $response;
    }

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

            throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['inventoryAdjustQuantities']['userErrors'], true));

        } else {

            $responseData = $responseData['data']['inventoryAdjustQuantities'];

        }

        return $responseData;
    }

}
