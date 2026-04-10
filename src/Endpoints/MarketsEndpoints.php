<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class MarketsEndpoints
{

    private $graphqlService;

    private $shopDomain;
    private $accessToken;

    public function __construct(string $shopDomain = null, string $accessToken = null)
    {

        if($shopDomain === null || $accessToken === null){
            throw new \InvalidArgumentException('Shop domain and access token must be provided.');
        }


        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;

        $this->graphqlService = new GraphqlService($this->shopDomain, $this->accessToken);

    }

    /** 
     * To get Markets and Catalogs use this function.
     */
    public function getMarkets($params = array())
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-01/queries/markets
        */

        $position = 'first';
        $cursorparam = '';
        $marketsFirst = 250;
        $catalogsFirst = 20;

        if(isset($params['limit'])){
            $marketsFirst = $params['limit'];
        }

        if(isset($params['catalog_limit'])){
            $catalogsFirst = $params['catalog_limit'];
        }

        if(isset($params['cursor'])){

            if(isset($params['direction']) && $params['direction'] == 'next'){
                $cursorparam = "after: \"{$params['cursor']}\"";
            }

            if(isset($params['direction']) && $params['direction'] == 'prev'){
                $position = 'last';
                $cursorparam = "before: \"{$params['cursor']}\"";
            }

        }

        $marketsquery = <<<"GRAPHQL"
            query GetMarkets {
                markets($position: $marketsFirst, $cursorparam) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                    }
                    edges {
                        cursor
                        node {
                            id
                            name
                            status
                            type
                            currencySettings {
                                localCurrencies
                                baseCurrency {
                                    currencyCode
                                }
                                roundingEnabled
                            }
                            catalogs(first: $catalogsFirst) {
                                pageInfo {
                                    hasNextPage
                                    hasPreviousPage
                                }
                                edges {
                                    cursor
                                    node {
                                        id
                                        title
                                        status
                                        priceList {
                                            id
                                            name
                                            currency
                                            fixedPricesCount
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($marketsquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);

        } else {

            $marketsResponseData = $responseData['data']['markets'];

        }

        $marketsData = [
            'pageInfo' => $marketsResponseData['pageInfo'],
            'markets' => array_map(function ($edge) {
                $market = $edge['node'];
                $market['id'] = str_replace('gid://shopify/Market/', '', $market['id']);
                $market['cursor'] = $edge['cursor'];
                if (isset($market['catalogs']['edges']) && is_array($market['catalogs']['edges'])) {
                    $market['catalogs']['nodes'] = array_map(function ($catalogEdge) {
                        $catalog = isset($catalogEdge['node']) ? $catalogEdge['node'] : array();
                        $catalog['cursor'] = isset($catalogEdge['cursor']) ? $catalogEdge['cursor'] : null;
                        return $catalog;
                    }, $market['catalogs']['edges']);
                    unset($market['catalogs']['edges']);
                }
                return $market;
            }, $marketsResponseData['edges'])
        ];

        return $marketsData;
    }

    /**
     * To get Market Catalog Price Lists use this function.
     */
    public function getMarketCatalogPriceLists($params = array())
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-01/queries/markets
        */

        $marketIds = isset($params['market_ids']) && is_array($params['market_ids']) ? $params['market_ids'] : array();

        $marketsFirst = 50;

        if(isset($params['limit'])){
            $marketsFirst = $params['limit'];
        }

        $position = 'first';
        $cursorparam = '';

        if(isset($params['cursor'])){

            if(isset($params['direction']) && $params['direction'] == 'next'){
                $cursorparam = "after: \"{$params['cursor']}\"";
            }

            if(isset($params['direction']) && $params['direction'] == 'prev'){
                $position = 'last';
                $cursorparam = "before: \"{$params['cursor']}\"";
            }

        }

        $marketIdsCount = count($marketIds);
        $marketsFirst = ($marketIdsCount > $marketsFirst) ? $marketIdsCount : $marketsFirst;

        $marketsPageInfo = array();

        $marketQueryParts = array();
        foreach($marketIds as $marketId){
            $marketQueryParts[] = 'id:' . str_replace('gid://shopify/Market/', '', $marketId);
        }

        // Add markets query filter only when IDs are provided; avoid sending query: "".
        $marketsArguments = "{$position}: {$marketsFirst}";
        if (!empty($marketQueryParts)) {
            $marketsArguments .= ', query: "' . implode(' OR ', $marketQueryParts) . '"';
        }
        if (!empty($cursorparam)) {
            $marketsArguments .= ', ' . $cursorparam;
        }

        $marketsquery = <<<"GRAPHQL"
            query GetMarketCatalogPriceLists {
                markets({$marketsArguments}) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                    }
                    edges {
                        cursor
                        node {
                            id
                            conditions {
                                regionsCondition {
                                    regions(first: 1) {
                                        edges {
                                            node {
                                                ... on MarketRegionCountry {
                                                    code
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            catalogs(first: 20) {
                                edges {
                                    node {
                                        priceList {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($marketsquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);

        } else {

            $marketCatalogPriceListsResponseData = $responseData['data']['markets'];

        }

        $marketsPageInfo = isset($marketCatalogPriceListsResponseData['pageInfo']) ? $marketCatalogPriceListsResponseData['pageInfo'] : array();
        $marketEdges = isset($marketCatalogPriceListsResponseData['edges']) ? $marketCatalogPriceListsResponseData['edges'] : array();

        $marketsList = array();

        foreach ($marketEdges as $edge) {

            $market = isset($edge['node']) ? $edge['node'] : array();
            $marketIdRaw = isset($market['id']) ? $market['id'] : '';

            $marketId = str_replace('gid://shopify/Market/', '', $marketIdRaw);

            $catalogsEdges = isset($market['catalogs']['edges']) ? $market['catalogs']['edges'] : array();
            $regionEdges = isset($market['conditions']['regionsCondition']['regions']['edges']) ? $market['conditions']['regionsCondition']['regions']['edges'] : array();

            $catalogNodes = array_map(function ($catalogEdge) {
                $catalog = isset($catalogEdge['node']) ? $catalogEdge['node'] : array();
                if(isset($catalog['priceList']['id'])){
                    $catalog['priceList']['id'] = str_replace('gid://shopify/PriceList/', '', $catalog['priceList']['id']);
                }
                return $catalog;
            }, $catalogsEdges);

            $countryCode = null;
            foreach ($regionEdges as $regionEdge) {
                $region = isset($regionEdge['node']) ? $regionEdge['node'] : array();
                if (isset($region['code']) && !empty($region['code'])) {
                    $countryCode = $region['code'];
                    break;
                }
            }

            $marketsList[] = [
                'id' => $marketId,
                'cursor' => isset($edge['cursor']) ? $edge['cursor'] : null,
                'countryCode' => $countryCode,
                'catalogs' => $catalogNodes,
            ];

        }

        $marketCatalogPriceListsData = [
            'pageInfo' => $marketsPageInfo,
            'markets' => $marketsList,
        ];

        return $marketCatalogPriceListsData;
    }

    /**
     * To get prices for specific variant IDs across multiple Price Lists in a single bulk request use this function.
     */
    public function getMarketPriceListsPrices($priceListData = array(), $variantIds = array())
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-01/queries/priceList
        */

        $priceListIds = [];
        $priceListCountryCodes = [];
        if (!empty($priceListData) && is_array($priceListData)) {
            foreach ($priceListData as $marketPriceLists) {
                if (!is_array($marketPriceLists)) {
                    continue;
                }
                foreach ($marketPriceLists as $priceListId => $countryCode) {
                    if ($priceListId === null || $priceListId === '') {
                        continue;
                    }
                    $priceListIdString = (string) $priceListId;
                    if (!isset($priceListCountryCodes[$priceListIdString])) {
                        $priceListIds[] = $priceListIdString;
                    }
                    $priceListCountryCodes[$priceListIdString] = $countryCode;
                }
            }
        }

        $priceListIds = array_values(array_unique($priceListIds));
        if (empty($priceListIds) || empty($variantIds)) {
            return [];
        }

        $normalizedIds = array_map(function($variantId){
            return str_replace('gid://shopify/ProductVariant/', '', $variantId);
        }, $variantIds);

        $variantIdQuery = implode(' OR ', array_map(function($id){
            return "variant_id:{$id}";
        }, $normalizedIds));

        $pricesFirst = max(1, count($normalizedIds));

        $queryParts = '';
        foreach($priceListIds as $priceListId){
            $normalizedId = (strpos($priceListId, 'gid://shopify/PriceList/') === false) ? "gid://shopify/PriceList/{$priceListId}" : $priceListId;

            $alias = 'pl_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $priceListId);
            $countryCode = $priceListCountryCodes[$priceListId];

            $queryParts .= "
            {$alias}: priceList(id: \"{$normalizedId}\") {
                id
                name
                currency
                prices(first: {$pricesFirst}, query: \"{$variantIdQuery}\") {
                    edges {
                        cursor
                        node {
                            price {
                                amount
                                currencyCode
                            }
                            compareAtPrice {
                                amount
                                currencyCode
                            }
                            originType
                            variant {
                                id
                                contextualPricing(context: { country: {$countryCode} }) {
                                    price {
                                        amount
                                        currencyCode
                                    }
                                    compareAtPrice {
                                        amount
                                        currencyCode
                                    }
                                }
                            }
                        }
                    }
                }
            }";
        }

        $priceListsBulkQuery = <<<"GRAPHQL"
            query GetPriceListsPricesByVariantId {
                {$queryParts}
            }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($priceListsBulkQuery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])){

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);

        } else {

            $priceListsBulkResponseData = $responseData['data'];

        }
        
        $priceListsPricesData = array();

        foreach($priceListIds as $priceListId){

            $alias = 'pl_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $priceListId);
            $priceListResponseData = isset($priceListsBulkResponseData[$alias]) ? $priceListsBulkResponseData[$alias] : array();

            if(empty($priceListResponseData)){
                continue;
            }

            $priceEdges = $priceListResponseData['prices']['edges'] ?? [];
            $priceListCurrency = $priceListResponseData['currency'] ?? null;

            $priceNodes = array_map(function ($priceEdge) use ($priceListCurrency) {
                $row = $priceEdge['node'] ?? [];
                $priceListPrice = $row['price'] ?? null;
                $priceListCompareAtPrice = $row['compareAtPrice'] ?? null;
                $contextualPrice = $row['variant']['contextualPricing']['price'] ?? null;
                $contextualCompareAtPrice = $row['variant']['contextualPricing']['compareAtPrice'] ?? null;

                $priceListPriceCurrency = $priceListPrice['currencyCode'] ?? null;
                $contextualPriceCurrency = $contextualPrice['currencyCode'] ?? null;

                // Prefer price-list values; switch to contextual only when top-level currency mismatches price list currency.
                $hasPriceListCurrency = $priceListCurrency !== null;
                $priceListValueMismatchesListCurrency = $priceListPriceCurrency !== null && $priceListPriceCurrency !== $priceListCurrency;
                $contextualMatchesListCurrency = $contextualPriceCurrency === $priceListCurrency;
                $useContextual = $hasPriceListCurrency && $priceListValueMismatchesListCurrency && $contextualMatchesListCurrency;

                $row['price'] = $useContextual ? $contextualPrice : $priceListPrice;
                $row['compareAtPrice'] = $useContextual ? $contextualCompareAtPrice : $priceListCompareAtPrice;

                if (isset($row['variant']['id'])){
                    $row['variant']['id'] = str_replace('gid://shopify/ProductVariant/', '', $row['variant']['id']);
                }
                $row['cursor'] = $priceEdge['cursor'] ?? null;
                return $row;
            }, $priceEdges);

            $priceListsPricesData[$priceListId] = [
                'priceList' => [
                    'id' => str_replace('gid://shopify/PriceList/', '', isset($priceListResponseData['id']) ? $priceListResponseData['id'] : ''),
                    'name' => isset($priceListResponseData['name']) ? $priceListResponseData['name'] : '',
                    'currency' => isset($priceListResponseData['currency']) ? $priceListResponseData['currency'] : '',
                    'prices' => $priceNodes,
                ],
            ];

        }

        return $priceListsPricesData;
    }

    /**
     * To update Price List Prices use this function.
     */
    public function priceListFixedPricesUpdate($priceListId, $pricesToAdd = [], $variantIdsToDelete = [])
    {
        /*
        Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-01/mutations/priceListFixedPricesUpdate
        */

        $normalizedPriceListId = $priceListId;
        if (strpos($priceListId, 'gid://shopify/PriceList/') === false) {
            $normalizedPriceListId = "gid://shopify/PriceList/{$priceListId}";
        }

        $normalizedAdds = array();
        foreach ($pricesToAdd as $priceInput) {
            if (!isset($priceInput['variantId'], $priceInput['currencyCode'], $priceInput['price'])) {
                continue;
            }
            if ($priceInput['variantId'] === '' || $priceInput['currencyCode'] === '' || $priceInput['price'] === '') {
                continue;
            }
            $entry = array();
            if (strpos($priceInput['variantId'], 'gid://shopify/ProductVariant/') !== false) {
                $entry['variantId'] = $priceInput['variantId'];
            } else {
                $entry['variantId'] = "gid://shopify/ProductVariant/{$priceInput['variantId']}";
            }
            $currencyCode = $priceInput['currencyCode'];
            $entry['price'] = array(
                'amount' => $priceInput['price'],
                'currencyCode' => $currencyCode,
            );

            if (isset($priceInput['compareAtPrice']) && $priceInput['compareAtPrice'] !== null && $priceInput['compareAtPrice'] !== '') {
                $entry['compareAtPrice'] = array(
                    'amount' => $priceInput['compareAtPrice'],
                    'currencyCode' => $currencyCode,
                );
            }

            $normalizedAdds[] = $entry;
        }

        $normalizedDeletes = array();
        foreach ($variantIdsToDelete as $variantId) {
            if (strpos($variantId, 'gid://shopify/ProductVariant/') !== false) {
                $normalizedDeletes[] = $variantId;
            } else {
                $normalizedDeletes[] = "gid://shopify/ProductVariant/{$variantId}";
            }
        }

        $variables = array(
            'priceListId' => $normalizedPriceListId,
            'pricesToAdd' => $normalizedAdds,
            'variantIdsToDelete' => $normalizedDeletes,
        );

        $mutation = <<<'GRAPHQL'
            mutation priceListFixedPricesUpdate(
                $priceListId: ID!,
                $pricesToAdd: [PriceListPriceInput!]!,
                $variantIdsToDelete: [ID!]!
            ) {
                priceListFixedPricesUpdate(
                    priceListId: $priceListId,
                    pricesToAdd: $pricesToAdd,
                    variantIdsToDelete: $variantIdsToDelete
                ) {
                    pricesAdded {
                        variant {
                            id
                        }
                        price {
                            amount
                            currencyCode
                        }
                        compareAtPrice {
                            amount
                            currencyCode
                        }
                    }
                    deletedFixedPriceVariantIds
                }
            }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($mutation, $variables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);

        } else {

            $priceListFixedPricesUpdateData = $responseData['data']['priceListFixedPricesUpdate'];


        }

        return $priceListFixedPricesUpdateData;
    }
    
}


