<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;

class InventoryEndpoints
{
    private $graphqlService;
    private $shopDomain;
    private $accessToken;

    public function __construct(?string $shopDomain = null, ?string $accessToken = null)
    {
        if ($shopDomain === null || $accessToken === null) {
            throw new \InvalidArgumentException('Shop domain and access token must be provided.');
        }

        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
        $this->graphqlService = new GraphqlService($this->shopDomain, $this->accessToken);
    }

    /**
     * Convert ID to GID format for InventoryItem
     *
     * @param string $id
     * @return string
     */
    private function formatInventoryItemGid(string $id): string
    {
        if (strpos($id, 'gid://shopify/InventoryItem/') === false) {
            return "gid://shopify/InventoryItem/{$id}";
        }
        return $id;
    }

    /**
     * Convert ID to GID format for Location
     *
     * @param string $id
     * @return string
     */
    private function formatLocationGid(string $id): string
    {
        if (strpos($id, 'gid://shopify/Location/') === false) {
            return "gid://shopify/Location/{$id}";
        }
        return $id;
    }

    /**
     * Extract numeric ID from GID format
     *
     * @param string $gid
     * @param string $type
     * @return string
     */
    private function extractIdFromGid(string $gid, string $type): string
    {
        return str_replace("gid://shopify/{$type}/", '', $gid);
    }

    /**
     * Handle GraphQL response errors
     *
     * @param array $responseData
     * @param string $operationName
     * @return void
     * @throws GraphqlException
     */
    private function handleGraphqlErrors(array $responseData, string $operationName = ''): void
    {
        if (isset($responseData['errors']) && !empty($responseData['errors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);
        }

        if (!empty($operationName) && isset($responseData['data'][$operationName]['userErrors']) && !empty($responseData['data'][$operationName]['userErrors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data'][$operationName]['userErrors']);
        }
    }

    /**
     * To get Inventory Items use this function.
     *
     * @param array $params
     * @return array
     * @throws GraphqlException
     */
    public function getInventoryItems($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/inventoryItems
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/inventorylevel#get-inventory-levels
        */

        $query = '';
        if (!empty($params['inventory_item_id'])) {
            $inventoryItemId = $this->extractIdFromGid($params['inventory_item_id'], 'InventoryItem');
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
        $this->handleGraphqlErrors($responseData);

        $responseData = $responseData['data']['inventoryItems']['edges'];
        $finalarray = [];

        foreach ($responseData as $inventoryItem) {
            $response = [];
            $locations = $inventoryItem['node']['inventoryLevels']['edges'];

            foreach ($locations as $lkey => $location) {
                $response['inventory_item_id'] = $this->extractIdFromGid($inventoryItem['node']['id'], 'InventoryItem');
                $response['tracked'] = $inventoryItem['node']['tracked'];
                $response['sku'] = $inventoryItem['node']['sku'];
                $response['requires_shipping'] = $inventoryItem['node']['requiresShipping'];
                $response['location_id'] = $this->extractIdFromGid($location['node']['location']['id'], 'Location');
                $response['location_name'] = $location['node']['location']['name'];
                $response['available'] = $location['node']['quantities'][0]['quantity'] ?? 0;

                $finalarray[$lkey] = $response;
            }
        }

        return $finalarray;
    }

    /**
     * To get Inventory Item use this function.
     *
     * @param string $inventoryItemId
     * @return array
     * @throws GraphqlException
     */
    public function getInventoryItem($inventoryItemId)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/inventoryItem
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/inventoryitem#get-inventory-items-inventory-item-id
        */

        $inventoryItemId = $this->extractIdFromGid($inventoryItemId, 'InventoryItem');

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
        $this->handleGraphqlErrors($responseData);

        $responseData = $responseData['data']['inventoryItem'];
        $response = [];
        $response['id'] = $this->extractIdFromGid($responseData['id'], 'InventoryItem');
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
     *
     * @param array $params
     * @return array
     * @throws GraphqlException
     */
    public function inventorySetQuantities($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/inventorySetQuantities?language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/inventorylevel#post-inventory-levels-adjust
        */

        if (empty($params['location_id']) || empty($params['inventory_item_id'])) {
            throw new \InvalidArgumentException('location_id and inventory_item_id are required.');
        }

        $inventoryadjustvariables = [
            'input' => [
                'reason' => 'correction',
                'name' => 'available',
                'ignoreCompareQuantity' => true,
                'quantities' => [
                    [
                        'quantity' => $params['available'] ?? 0,
                        'inventoryItemId' => $this->formatInventoryItemGid($params['inventory_item_id']),
                        'locationId' => $this->formatLocationGid($params['location_id'])
                    ]
                ]
            ]
        ];

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
        $this->handleGraphqlErrors($responseData);

        $userErrors = $responseData['data']['inventorySetQuantities']['userErrors'] ?? [];
        if (!empty($userErrors)) {
            $errorMessage = $userErrors[0]['message'] ?? '';
            if ($errorMessage === 'The specified inventory item is not stocked at the location.') {
                // Try to activate the inventory item at the location first, then retry
                $this->inventoryActivate($params);
                // Retry setting quantities after activation
                $responseData = $this->graphqlService->graphqlQueryThalia($inventoryadjustquery, $inventoryadjustvariables);
                $this->handleGraphqlErrors($responseData);
                $userErrors = $responseData['data']['inventorySetQuantities']['userErrors'] ?? [];
                if (!empty($userErrors)) {
                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $userErrors);
                }
            } else {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $userErrors);
            }
        }

        return $responseData['data']['inventorySetQuantities'];
    }

    /**
     * To activate Inventory Item at a location use this function.
     *
     * @param array $params
     * @return array
     * @throws GraphqlException
     */
    public function inventoryActivate($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-10/mutations/inventoryActivate
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/inventorylevel#post-inventory-levels
        */

        if (empty($params['inventory_item_id']) || empty($params['location_id'])) {
            throw new \InvalidArgumentException('inventory_item_id and location_id are required.');
        }

        $variables = [
            'inventoryItemId' => $this->formatInventoryItemGid($params['inventory_item_id']),
            'locationId' => $this->formatLocationGid($params['location_id']),
        ];

        if (isset($params['available']) && $params['available'] !== null) {
            $variables['available'] = (int)$params['available'];
        }

        $inventoryactivatequery = <<<'GRAPHQL'
            mutation ActivateInventoryItem($inventoryItemId: ID!, $locationId: ID!, $available: Int) {
                inventoryActivate(inventoryItemId: $inventoryItemId, locationId: $locationId, available: $available) {
                    inventoryLevel {
                        id
                        quantities(names: ["available"]) {
                            name
                            quantity
                        }
                        item {
                            id
                        }
                        location {
                            id
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($inventoryactivatequery, $variables);
        $this->handleGraphqlErrors($responseData, 'inventoryActivate');

        return $responseData['data']['inventoryActivate']['inventoryLevel'];
    }


    /**
     * To adjust Inventory Quantities use this function. (Don't Use this)
     *
     * @param array $params
     * @return array
     * @throws GraphqlException
     */
    public function inventoryAdjustQuantities($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/inventoryAdjustQuantities
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/inventorylevel#post-inventory-levels-adjust
        */

        if (empty($params['location_id']) || empty($params['inventory_item_id'])) {
            throw new \InvalidArgumentException('location_id and inventory_item_id are required.');
        }

        $inventoryadjustvariables = [
            'input' => [
                'reason' => 'correction',
                'name' => 'available',
                'changes' => [
                    'delta' => $params['available_adjustment'] ?? 0,
                    'inventoryItemId' => $this->formatInventoryItemGid($params['inventory_item_id']),
                    'locationId' => $this->formatLocationGid($params['location_id'])
                ]
            ]
        ];

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
        $this->handleGraphqlErrors($responseData, 'inventoryAdjustQuantities');

        return $responseData['data']['inventoryAdjustQuantities'];
    }

    /**
     * To get Inventory Items with all levels use this function.
     *
     * @param array $params
     * @return array
     * @throws GraphqlException
     */
    public function getInventoryItemsWithAllLevels($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/inventoryItems
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/inventorylevel#get-inventory-levels
        */

        $query = '';
        if (!empty($params['inventory_item_id'])) {
            $inventoryItemId = $this->extractIdFromGid($params['inventory_item_id'], 'InventoryItem');
            $query = 'query: "id:' . $inventoryItemId . '"';
        }

        $levels = ["available", "incoming", "committed", "damaged", "on_hand", "quality_control", "reserved", "safety_stock"];
        $querylevels = json_encode($levels);
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
                                    quantities(names: $querylevels) {
                                        quantity
                                    }
                                    location {
                                        id
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
        $this->handleGraphqlErrors($responseData);

        $responseData = $responseData['data']['inventoryItems']['edges'];
        $finalarray = [];

        foreach ($responseData as $inventoryItem) {
            $response = [];
            $locations = $inventoryItem['node']['inventoryLevels']['edges'];

            foreach ($locations as $lkey => $location) {
                $response['inventory_item_id'] = $this->extractIdFromGid($inventoryItem['node']['id'], 'InventoryItem');
                $response['tracked'] = $inventoryItem['node']['tracked'];
                $response['sku'] = $inventoryItem['node']['sku'];
                $response['requires_shipping'] = $inventoryItem['node']['requiresShipping'];
                $response['location_id'] = $this->extractIdFromGid($location['node']['location']['id'], 'Location');
                $response['location_name'] = $location['node']['location']['name'];

                foreach ($levels as $levelindex => $level) {
                    $response[$level] = $location['node']['quantities'][$levelindex]['quantity'] ?? 0;
                }

                $finalarray[$lkey] = $response;
            }
        }

        return $finalarray;
    }

}
