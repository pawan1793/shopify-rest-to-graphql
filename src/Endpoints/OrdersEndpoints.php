<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class OrdersEndpoints
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
     * To get Orders use this function.
     */
    public function getOrders($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/orders
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/order#get-orders
        */

        $position = 'first';
        $cursorparam = '';
        $limit = 250;

        if (isset($param['limit'])) {
            $limit = $param['limit'];
        }

        if (isset($param['cursor'])) {
            if (isset($param['direction']) && $param['direction'] == 'next') {
                $cursorparam = "after: \"{$param['cursor']}\"";
            }

            if (isset($param['direction']) && $param['direction'] == 'prev') {
                $position = 'last';
                $cursorparam = "before: \"{$param['cursor']}\"";
            }
        }

        $filters = [];

        if (!empty($param['query']['status'])) {
            $filters[] = "status:{$param['query']['status']}";
        }

        if (!empty($param['query']['created_at'])) {
            $filters[] = "created_at:{$param['query']['created_at']}";
        }

        if (!empty($param['query']['updated_at'])) {
            $filters[] = "updated_at:{$param['query']['updated_at']}";
        }

        if (!empty($param['query']['id'])) {
            $filters[] = "id:{$param['query']['id']}";
        }

        if (!empty($param['query']['name'])) {
            $filters[] = "name:{$param['query']['name']}";
        }

        if (!empty($param['query']['financial_status'])) {
            $filters[] = "financial_status:{$param['query']['financial_status']}";
        }

        if (!empty($param['query']['fulfillment_status'])) {
            $filters[] = "fulfillment_status:{$param['query']['fulfillment_status']}";
        }

        $queryString = !empty($filters) ? implode(" AND ", $filters) : "";

        $orderFields = implode("\n", $param['fields']);

        $orderQuery = <<<QUERY
            query {
                orders($position: $limit, query: "$queryString" $cursorparam) {
                    edges {
                        cursor
                        node {
                            $orderFields
                        }
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                    }
                }
            }
        QUERY;

        $responseData = $this->graphqlService->graphqlQueryThalia($orderQuery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $ordersData = $responseData['data']['orders']['edges'];

            $ordersResponse = array();

            foreach ($ordersData as $key => $order) {
                $orderResponse = [];
                $orderResponse['id'] = str_replace('gid://shopify/Order/', '', $order['node']['id']) ?? '';
                $orderResponse['admin_graphql_api_id'] = $order['node']['id'] ?? '';
                $orderResponse['cancel_reason'] = $order['node']['cancelReason'] ?? '';
                $orderResponse['cancelled_at'] = $order['node']['cancelledAt'] ?? '';
                $orderResponse['closed_at'] = $order['node']['closedAt'] ?? '';
                $orderResponse['processed_at'] = $order['node']['processedAt'] ?? '';
                $orderResponse['created_at'] = $order['node']['createdAt'] ?? '';
                $orderResponse['updated_at'] = $order['node']['updatedAt'] ?? '';
                $orderResponse['currency'] = $order['node']['currencyCode'] ?? '';
                $orderResponse['discount_codes'] = $order['node']['discountCodes'] ?? '';
                $orderResponse['fulfillment_status'] = isset($order['node']['displayFulfillmentStatus']) ? ucfirst(strtolower($order['node']['displayFulfillmentStatus'])) : '';
                $orderResponse['financial_status'] = isset($order['node']['displayFinancialStatus']) ? ucfirst(strtolower($order['node']['displayFinancialStatus'])) : '';
                $orderResponse['name'] = $order['node']['name'] ?? '';
                $orderResponse['note'] = $order['node']['note'] ?? '';
                $orderResponse['confirmation_number'] = $order['node']['confirmationNumber'] ?? '';
                $orderResponse['payment_gateway_names'] = $order['node']['paymentGatewayNames'] ?? '';
                $orderResponse['phone'] = $order['node']['phone'] ?? '';
                $orderResponse['tags'] = $order['node']['tags'] ?? '';
                $orderResponse['email'] = $order['node']['email'] ?? '';
                $orderResponse['customer'] = isset($order['node']['customer']) && is_array($order['node']['customer']) ? [
                    'first_name' => $order['node']['customer']['firstName'] ?? null,
                    'last_name' => $order['node']['customer']['lastName'] ?? null,
                    'note' => $order['node']['customer']['note'] ?? null,
                    'email' => $order['node']['customer']['email'] ?? null,
                    'phone' => $order['node']['customer']['phone'] ?? null,
                ] : [];
                $orderResponse['tax_lines'] = $order['node']['taxLines'] ?? '';
                $orderResponse['total_outstanding'] = $order['node']['totalOutstandingSet']['presentmentMoney']['amount'] ?? '';
                $orderResponse['total_price'] = $order['node']['totalPriceSet']['presentmentMoney']['amount'] ?? '';
                $orderResponse['total_discounts'] = $order['node']['totalDiscountsSet']['presentmentMoney']['amount'] ?? '';
                $orderResponse['note_attributes'] = $order['node']['customAttributes'] ?? '';
                $orderResponse['discount_applications'] = isset($order['node']['discountApplications']['edges']) && is_array($order['node']['discountApplications']['edges']) ? array_map(function ($discount) {
                    return [
                        'index' => $discount['node']['index'] ?? '',
                        'allocation_method' => strtolower($discount['node']['allocationMethod']) ?? '',
                        'target_selection' => strtolower($discount['node']['targetSelection']) ?? '',
                        'target_type' => strtolower($discount['node']['targetType']) ?? '',
                        'value' => isset($discount['node']['value']['amount'])
                            ? $discount['node']['value']['amount']
                            : (isset($discount['node']['value']['percentage']) ? $discount['node']['value']['percentage'] : ''),
                        'value_type' => isset($discount['node']['value']['percentage']) ? 'percentage' : 'fixed_amount' ?? '',
                    ];
                }, $order['node']['discountApplications']['edges']) : [];
                //$orderResponse['fulfillments'] = $order['node']['fulfillments'] ?? '';
                $orderResponse['fulfillments'] = isset($order['node']['fulfillments']) && is_array($order['node']['fulfillments']) ? array_map(function ($fulfillment) {
                    return [
                        'id' => str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id'] ?? ''),
                        'admin_graphql_api_id' => $fulfillment['id'] ?? '',
                        'created_at' => $fulfillment['createdAt'] ?? '',
                        'shipment_status' => $fulfillment['displayStatus'] ?? '',
                        'name' => $fulfillment['name'] ?? '',
                        'status' => $fulfillment['status'] ?? '',
                        'updated_at' => $fulfillment['updatedAt'] ?? '',
                        'tracking_number' => isset($fulfillment['trackingInfo'][0]['number']) ? $fulfillment['trackingInfo'][0]['number'] : '',
                        'tracking_url' => isset($fulfillment['trackingInfo'][0]['url']) ? $fulfillment['trackingInfo'][0]['url'] : '',
                        'tracking_company' => isset($fulfillment['trackingInfo'][0]['company']) ? $fulfillment['trackingInfo'][0]['company'] : '',
                    ];
                }, $order['node']['fulfillments']) : [];

                $fulfillableQuantities = [];
                if (isset($order['node']['fulfillmentOrders']['edges']) && is_array($order['node']['fulfillmentOrders']['edges'])) {
                    foreach ($order['node']['fulfillmentOrders']['edges'] as $fulfillmentOrder) {
                        if (!empty($fulfillmentOrder['node']['lineItems']['edges'])) {
                            foreach ($fulfillmentOrder['node']['lineItems']['edges'] as $orderLineItem) {
                                $lineItemId = str_replace('gid://shopify/LineItem/', '', $orderLineItem['node']['lineItem']['id']);
                                $remainingQty = $orderLineItem['node']['remainingQuantity'] ?? 0;
                                $fulfillableQuantities[$lineItemId] = $remainingQty;
                            }
                        }
                    }
                }
                $orderResponse['line_items'] = isset($order['node']['lineItems']['edges']) && is_array($order['node']['lineItems']['edges']) ? array_map(function ($item) use ($fulfillableQuantities) {
                    $lineItemId = isset($item['node']['id']) ? str_replace('gid://shopify/LineItem/', '', $item['node']['id']) : '';
                    return [
                        'id' => isset($item['node']['id']) ? str_replace('gid://shopify/LineItem/', '', $item['node']['id']) : '',
                        'admin_graphql_api_id' => $item['node']['id'] ?? '',
                        'current_quantity' => $item['node']['currentQuantity'] ?? '',
                        'fulfillable_quantity' => $fulfillableQuantities[$lineItemId] ?? 0,
                        'fulfillment_status' => $item['node']['fulfillmentStatus'] ?? '',
                        'name' => $item['node']['name'] ?? '',
                        'product_id' => isset($item['node']['product']['id']) ? str_replace('gid://shopify/Product/', '', $item['node']['product']['id']) : '',
                        'quantity' => $item['node']['quantity'] ?? '',
                        'requires_shipping' => $item['node']['requiresShipping'] ?? '',
                        'sku' => $item['node']['sku'] ?? '',
                        'taxable' => $item['node']['taxable'] ?? '',
                        'title' => $item['node']['title'] ?? '',
                        'properties' => isset($item['node']['customAttributes']) && is_array($item['node']['customAttributes']) ? [
                            'name' => $item['node']['customAttributes']['key'] ?? null,
                            'value' => $item['node']['customAttributes']['value'] ?? null,
                        ] : [],
                        'price' => $item['node']['originalUnitPriceSet']['shopMoney']['amount'] ?? '',
                        'total_discount_set' => $item['node']['totalDiscountSet']['shopMoney'] ?? '',
                        'variant_id' => isset($item['node']['variant']['id']) ? str_replace('gid://shopify/ProductVariant/', '', $item['node']['variant']['id']) : '',
                        'variant_title' => $item['node']['variant']['title'] ?? '',
                        'vendor' => $item['node']['vendor'] ?? '',
                        'tax_lines' => $item['node']['taxLines'] ?? [],
                        'discount_allocations' => isset($item['node']['discountAllocations']) && is_array($item['node']['discountAllocations']) ? array_map(function ($allocation) {
                            return [
                                'amount' => $allocation['allocatedAmountSet']['presentmentMoney']['amount'] ?? '',
                            ];
                        }, $item['node']['discountAllocations']) : [],
                    ];
                }, $order['node']['lineItems']['edges']) : [];
                $orderResponse['refunds'] = isset($order['node']['refunds']) && is_array($order['node']['refunds']) ? array_map(function ($refund) {
                    return [
                        'id' => isset($refund['id']) ? str_replace('gid://shopify/Refund/', '', $refund['id']) : '',
                        'admin_graphql_api_id' => $refund['id'] ?? '',
                        'created_at' => $refund['createdAt'] ?? '',
                        'note' => $refund['note'] ?? '',
                        'order_id' => isset($refund['order']['id']) ? str_replace('gid://shopify/Order/', '', $refund['order']['id']) : '',
                        'order_adjustments' => isset($refund['orderAdjustments']['edges']) && is_array($refund['orderAdjustments']['edges']) ? array_map(function ($adjustmentItem) {
                            return [
                                'amount' => $adjustmentItem['node']['amountSet']['presentmentMoney']['amount'] ?? '',
                                'kind' => $adjustmentItem['node']['reason'] ?? '',
                            ];
                        }, $refund['orderAdjustments']['edges']) : [],
                        'refund_shipping_lines' => isset($refund['refundShippingLines']['edges']) && is_array($refund['refundShippingLines']['edges']) ? array_map(function ($shippingLine) {
                            return [
                                'amount' => $shippingLine['node']['shippingLine']['originalPriceSet']['presentmentMoney']['amount'] ?? '',
                            ];
                        }, $refund['refundShippingLines']['edges']) : [],
                        'refund_line_items' => isset($refund['refundLineItems']['edges']) && is_array($refund['refundLineItems']['edges']) ? array_map(function ($refundLineItem) {
                            $node = $refundLineItem['node'] ?? [];
                            $lineItem = $node['lineItem'] ?? [];

                            return [
                                'id' => isset($node['id']) ? str_replace('gid://shopify/RefundLineItem/', '', $node['id']) : '',
                                'quantity' => $node['quantity'] ?? '',
                                'line_item_id' => isset($lineItem['id']) ? str_replace('gid://shopify/LineItem/', '', $lineItem['id']) : '',
                                'line_item' => [
                                    'id' => isset($lineItem['id']) ? str_replace('gid://shopify/LineItem/', '', $lineItem['id']) : '',
                                    'title' => $lineItem['title'] ?? '',
                                    'name' => $lineItem['name'] ?? '',
                                    'current_quantity' => $lineItem['currentQuantity'] ?? '',
                                    'quantity' => $lineItem['quantity'] ?? '',
                                    'refundable_quantity' => $lineItem['refundableQuantity'] ?? '',
                                    'variant_title' => $lineItem['variantTitle'] ?? '',
                                    'variant_id' => isset($lineItem['variant']['id']) ? str_replace('gid://shopify/ProductVariant/', '', $lineItem['variant']['id']) : '',
                                    'product_id' => isset($lineItem['product']['id']) ? str_replace('gid://shopify/Product/', '', $lineItem['product']['id']) : '',
                                    'price' => $lineItem['originalUnitPriceSet']['presentmentMoney']['amount'] ?? '',
                                    'total_discount' => $lineItem['totalDiscountSet']['presentmentMoney']['amount'] ?? '',
                                ],
                            ];
                        }, $refund['refundLineItems']['edges']) : []
                    ];
                }, $order['node']['refunds']) : [];
                $orderResponse['billing_address'] = isset($order['node']['billingAddress']) && is_array($order['node']['billingAddress']) ? [
                    'id' => $order['node']['billingAddress']['id'] ?? '',
                    'first_name' => $order['node']['billingAddress']['firstName'] ?? '',
                    'last_name' => $order['node']['billingAddress']['lastName'] ?? '',
                    'address1' => $order['node']['billingAddress']['address1'] ?? '',
                    'address2' => $order['node']['billingAddress']['address2'] ?? '',
                    'phone' => $order['node']['billingAddress']['phone'] ?? '',
                    'city' => $order['node']['billingAddress']['city'] ?? '',
                    'zip' => $order['node']['billingAddress']['zip'] ?? '',
                    'province' => $order['node']['billingAddress']['province'] ?? '',
                    'country' => $order['node']['billingAddress']['country'] ?? '',
                    'company' => $order['node']['billingAddress']['company'] ?? '',
                    'latitude' => $order['node']['billingAddress']['latitude'] ?? '',
                    'longitude' => $order['node']['billingAddress']['longitude'] ?? '',
                    'name' => $order['node']['billingAddress']['name'] ?? '',
                    'country_code' => $order['node']['billingAddress']['countryCodeV2'] ?? '',
                    'province_code' => $order['node']['billingAddress']['provinceCode'] ?? '',
                ] : [];
                $orderResponse['shipping_address'] = isset($order['node']['shippingAddress']) && is_array($order['node']['shippingAddress']) ? [
                    'id' => $order['node']['shippingAddress']['id'] ?? '',
                    'name' => $order['node']['shippingAddress']['name'] ?? '',
                    'address1' => $order['node']['shippingAddress']['address1'] ?? '',
                    'address2' => $order['node']['shippingAddress']['address2'] ?? '',
                    'phone' => $order['node']['shippingAddress']['phone'] ?? '',
                    'city' => $order['node']['shippingAddress']['city'] ?? '',
                    'zip' => $order['node']['shippingAddress']['zip'] ?? '',
                    'province' => $order['node']['shippingAddress']['province'] ?? '',
                    'country' => $order['node']['shippingAddress']['country'] ?? '',
                    'company' => $order['node']['shippingAddress']['company'] ?? '',
                    'latitude' => $order['node']['shippingAddress']['latitude'] ?? '',
                    'longitude' => $order['node']['shippingAddress']['longitude'] ?? '',
                    'name' => $order['node']['shippingAddress']['name'] ?? '',
                    'country_code' => $order['node']['shippingAddress']['countryCodeV2'] ?? '',
                    'province_code' => $order['node']['shippingAddress']['provinceCode'] ?? '',
                ] : [];
                $orderResponse['shipping_lines'] = isset($order['node']['shippingLines']['edges']) && is_array($order['node']['shippingLines']['edges']) ? array_map(function($item) {
                    return [
                        'id' => $item['node']['id'] ?? '',
                        'title' => $item['node']['title'] ?? '',
                        'price' => $item['node']['originalPriceSet']['presentmentMoney']['amount'] ?? '',
                        'discount_allocations' => isset($item['node']['discountAllocations']) && is_array($item['node']['discountAllocations']) ? array_map(function($discount) {
                            return [
                                'amount' => $discount['allocatedAmountSet']['presentmentMoney']['amount'] ?? 0,
                            ];
                        }, $item['node']['discountAllocations']) : [],
                        'discounted_price' => isset($item['node']['discountedPriceSet']) && is_array($item['node']['discountedPriceSet']) ? array_map(function($discount) {
                            return [
                                'amount' => $discount['discountedPriceSet']['presentmentMoney']['amount'] ?? 0,
                            ];
                        }, $item['node']['discountedPriceSet']) : [],
                        'is_removed' => $item['node']['isRemoved'] ?? '',
                    ];
                }, $order['node']['shippingLines']['edges']) : [];
                $orderResponse['cursor'] = $order['cursor'] ?? '';

                $ordersResponse[] = $orderResponse;
            }

            $pageInfo = $responseData['data']['orders']['pageInfo'];

            $responseData = [
                'data' => $ordersResponse,
                'pageInfo' => $pageInfo,
            ];

            return $responseData;

        }

    }

    /**
     * To get Order by its ID use this function.
     */
    public function getOrder($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/order?example=Retrieve+a+specific+order
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/order#get-orders-order-id
        */

        $orderFields = implode("\n", $param['fields']);

        $orderQuery = <<<QUERY
            query GetOrderById(\$id: ID!) {
                order(id: \$id) {
                    $orderFields
                }
            }
        QUERY;

        $orderVariables = ['id' => "gid://shopify/Order/{$param['id']}"];

        $responseData = $this->graphqlService->graphqlQueryThalia($orderQuery, $orderVariables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $orderData = $responseData['data']['order'];

            $orderResponse = array();
            if(isset($orderData)) {
            $orderResponse['id'] = isset($orderData['id']) ? str_replace('gid://shopify/Order/', '', $orderData['id']) : '';
            $orderResponse['admin_graphql_api_id'] = $orderData['id'] ?? '';
            $orderResponse['cancel_reason'] = $orderData['cancelReason'] ?? '';
            $orderResponse['cancelled_at'] = $orderData['cancelledAt'] ?? '';
            $orderResponse['closed_at'] = $orderData['closedAt'] ?? '';
            $orderResponse['processed_at'] = $orderData['processedAt'] ?? '';
            $orderResponse['created_at'] = $orderData['createdAt'] ?? '';
            $orderResponse['updated_at'] = $orderData['updatedAt'] ?? '';
            $orderResponse['currency'] = $orderData['currencyCode'] ?? '';
            $orderResponse['discount_codes'] = $orderData['discountCodes'] ?? '';
            $orderResponse['fulfillment_status'] = isset($orderData['displayFulfillmentStatus']) ? ucfirst(strtolower($orderData['displayFulfillmentStatus'])) : '';
            $orderResponse['financial_status'] = isset($orderData['displayFinancialStatus']) ? ucfirst(strtolower($orderData['displayFinancialStatus'])) : '';
            $orderResponse['name'] = $orderData['name'] ?? '';
            $orderResponse['note'] = $orderData['note'] ?? '';
            $orderResponse['confirmation_number'] = $orderData['confirmationNumber'] ?? '';
            $orderResponse['payment_gateway_names'] = $orderData['paymentGatewayNames'] ?? '';
            $orderResponse['phone'] = $orderData['phone'] ?? '';
            $orderResponse['tags'] = $orderData['tags'] ?? '';
            $orderResponse['email'] = $orderData['email'] ?? '';
            $orderResponse['customer'] = isset($orderData['customer']) && is_array($orderData['customer']) ? [
                'first_name' => $orderData['customer']['firstName'] ?? null,
                'last_name' => $orderData['customer']['lastName'] ?? null,
                'note' => $orderData['customer']['note'] ?? null,
                'email' => $orderData['customer']['email'] ?? null,
                'phone' => $orderData['customer']['phone'] ?? null,
            ] : [];
            $orderResponse['tax_lines'] = $orderData['taxLines'] ?? '';
            $orderResponse['total_outstanding'] = $orderData['totalOutstandingSet']['presentmentMoney']['amount'] ?? '';
            $orderResponse['total_price'] = $orderData['totalPriceSet']['presentmentMoney']['amount'] ?? '';
            $orderResponse['total_discounts'] = $orderData['totalDiscountsSet']['presentmentMoney']['amount'] ?? '';
            $orderResponse['note_attributes'] = $orderData['customAttributes'] ?? '';
            $orderResponse['discount_applications'] = isset($orderData['discountApplications']['edges']) && is_array($orderData['discountApplications']['edges']) ? array_map(function ($discount) {
                return [
                    'index' => $discount['node']['index'] ?? '',
                    'allocation_method' => strtolower($discount['node']['allocationMethod']) ?? '',
                    'target_selection' => strtolower($discount['node']['targetSelection']) ?? '',
                    'target_type' => strtolower($discount['node']['targetType']) ?? '',
                    'value' => isset($discount['node']['value']['amount'])
                        ? $discount['node']['value']['amount']
                        : (isset($discount['node']['value']['percentage']) ? $discount['node']['value']['percentage'] : ''),

                    'value_type' => isset($discount['node']['value']['percentage']) ? 'percentage' : 'fixed_amount' ?? '',
                ];
            }, $orderData['discountApplications']['edges']) : [];
            //$orderResponse['fulfillments'] = $orderData['fulfillments'] ?? '';
            $orderResponse['fulfillments'] = isset($orderData['fulfillments']) && is_array($orderData['fulfillments']) ? array_map(function ($fulfillment) {
                return [
                    'id' => str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id'] ?? ''),
                    'admin_graphql_api_id' => $fulfillment['id'] ?? '',
                    'created_at' => $fulfillment['createdAt'] ?? '',
                    'shipment_status' => $fulfillment['displayStatus'] ?? '',
                    'name' => $fulfillment['name'] ?? '',
                    'status' => $fulfillment['status'] ?? '',
                    'updated_at' => $fulfillment['updatedAt'] ?? '',
                    'tracking_number' => isset($fulfillment['trackingInfo'][0]['number']) ? $fulfillment['trackingInfo'][0]['number'] : '',
                    'tracking_url' => isset($fulfillment['trackingInfo'][0]['url']) ? $fulfillment['trackingInfo'][0]['url'] : '',
                    'tracking_company' => isset($fulfillment['trackingInfo'][0]['company']) ? $fulfillment['trackingInfo'][0]['company'] : '',
                ];
            }, $orderData['fulfillments']) : [];
            $fulfillableQuantities = [];
            if (isset($orderData['fulfillmentOrders']['edges']) && is_array($orderData['fulfillmentOrders']['edges'])) {
                foreach ($orderData['fulfillmentOrders']['edges'] as $fulfillmentOrder) {
                    if (!empty($fulfillmentOrder['node']['lineItems']['edges'])) {
                        foreach ($fulfillmentOrder['node']['lineItems']['edges'] as $orderLineItem) {
                            $lineItemId = str_replace('gid://shopify/LineItem/', '', $orderLineItem['node']['lineItem']['id']);
                            $remainingQty = $orderLineItem['node']['remainingQuantity'] ?? 0;
                            $fulfillableQuantities[$lineItemId] = $remainingQty;
                        }
                    }
                }
            }
            $orderResponse['line_items'] = isset($orderData['lineItems']['edges']) && is_array($orderData['lineItems']['edges']) ? array_map(function ($item) use ($fulfillableQuantities) {
                $lineItemId = isset($item['node']['id']) ? str_replace('gid://shopify/LineItem/', '', $item['node']['id']) : '';
                return [
                    'id' => isset($item['node']['id']) ? str_replace('gid://shopify/LineItem/', '', $item['node']['id']) : '',
                    'admin_graphql_api_id' => $item['node']['id'] ?? '',
                    'current_quantity' => $item['node']['currentQuantity'] ?? '',
                    'fulfillable_quantity' => $fulfillableQuantities[$lineItemId] ?? 0,
                    'fulfillment_status' => $item['node']['fulfillmentStatus'] ?? '',
                    'name' => $item['node']['name'] ?? '',
                    'product_id' => isset($item['node']['product']['id']) ? str_replace('gid://shopify/Product/', '', $item['node']['product']['id']) : '',
                    'quantity' => $item['node']['quantity'] ?? '',
                    'requires_shipping' => $item['node']['requiresShipping'] ?? '',
                    'sku' => $item['node']['sku'] ?? '',
                    'taxable' => $item['node']['taxable'] ?? '',
                    'title' => $item['node']['title'] ?? '',
                    'properties' => isset($item['node']['customAttributes']) && is_array($item['node']['customAttributes']) ? [
                        'name' => $item['node']['customAttributes']['key'] ?? null,
                        'value' => $item['node']['customAttributes']['value'] ?? null,
                    ] : [],
                    'price' => $item['node']['originalUnitPriceSet']['shopMoney']['amount'] ?? '',
                    'total_discount_set' => $item['node']['totalDiscountSet']['shopMoney'] ?? '',
                    'variant_id' => isset($item['node']['variant']['id']) ? str_replace('gid://shopify/ProductVariant/', '', $item['node']['variant']['id']) : '',
                    'variant_title' => $item['node']['variant']['title'] ?? '',
                    'vendor' => $item['node']['vendor'] ?? '',
                    'tax_lines' => $item['node']['taxLines'] ?? [],
                    'discount_allocations' => isset($item['node']['discountAllocations']) && is_array($item['node']['discountAllocations']) ? array_map(function ($allocation) {
                        return [
                            'amount' => $allocation['allocatedAmountSet']['presentmentMoney']['amount'] ?? '',
                        ];
                    }, $item['node']['discountAllocations']) : [],
                ];
            }, $orderData['lineItems']['edges']) : [];
            $orderResponse['refunds'] = isset($orderData['refunds']) && is_array($orderData['refunds']) ? array_map(function ($refund) {
                return [
                    'id' => isset($refund['id']) ? str_replace('gid://shopify/Refund/', '', $refund['id']) : '',
                    'admin_graphql_api_id' => $refund['id'] ?? '',
                    'created_at' => $refund['createdAt'] ?? '',
                    'note' => $refund['note'] ?? '',
                    'order_id' => isset($refund['order']['id']) ? str_replace('gid://shopify/Order/', '', $refund['order']['id']) : '',
                    'order_adjustments' => isset($refund['orderAdjustments']['edges']) && is_array($refund['orderAdjustments']['edges']) ? array_map(function ($adjustmentItem) {
                        return [
                            'amount' => $adjustmentItem['node']['amountSet']['presentmentMoney']['amount'] ?? '',
                            'kind' => $adjustmentItem['node']['reason'] ?? '',
                        ];
                    }, $refund['orderAdjustments']['edges']) : [],
                    'refund_shipping_lines' => isset($refund['refundShippingLines']['edges']) && is_array($refund['refundShippingLines']['edges']) ? array_map(function ($shippingLine) {
                        return [
                            'amount' => $shippingLine['node']['shippingLine']['originalPriceSet']['presentmentMoney']['amount'] ?? '',
                        ];
                    }, $refund['refundShippingLines']['edges']) : [],
                    'refund_line_items' => isset($refund['refundLineItems']['edges']) && is_array($refund['refundLineItems']['edges']) ? array_map(function ($refundLineItem) {
                        $node = $refundLineItem['node'] ?? [];
                        $lineItem = $node['lineItem'] ?? [];

                        return [
                            'id' => isset($node['id']) ? str_replace('gid://shopify/RefundLineItem/', '', $node['id']) : '',
                            'quantity' => $node['quantity'] ?? '',
                            'line_item' => [
                                'id' => isset($lineItem['id']) ? str_replace('gid://shopify/LineItem/', '', $lineItem['id']) : '',
                                'title' => $lineItem['title'] ?? '',
                                'name' => $lineItem['name'] ?? '',
                                'current_quantity' => $lineItem['currentQuantity'] ?? '',
                                'quantity' => $lineItem['quantity'] ?? '',
                                'refundable_quantity' => $lineItem['refundableQuantity'] ?? '',
                                'variant_title' => $lineItem['variantTitle'] ?? '',
                                'variant_id' => isset($lineItem['variant']['id']) ? str_replace('gid://shopify/ProductVariant/', '', $lineItem['variant']['id']) : '',
                                'product_id' => isset($lineItem['product']['id']) ? str_replace('gid://shopify/Product/', '', $lineItem['product']['id']) : '',
                                'price' => $lineItem['originalUnitPriceSet']['presentmentMoney']['amount'] ?? '',
                                'total_discount' => $lineItem['totalDiscountSet']['presentmentMoney']['amount'] ?? '',
                            ],
                        ];
                    }, $refund['refundLineItems']['edges']) : []
                ];
            }, $orderData['refunds']) : [];
            $orderResponse['billing_address'] = isset($orderData['billingAddress']) && is_array($orderData['billingAddress']) ? [
                'id' => $orderData['billingAddress']['id'] ?? '',
                'first_name' => $orderData['billingAddress']['firstName'] ?? '',
                'last_name' => $orderData['billingAddress']['lastName'] ?? '',
                'address1' => $orderData['billingAddress']['address1'] ?? '',
                'address2' => $orderData['billingAddress']['address2'] ?? '',
                'phone' => $orderData['billingAddress']['phone'] ?? '',
                'city' => $orderData['billingAddress']['city'] ?? '',
                'zip' => $orderData['billingAddress']['zip'] ?? '',
                'province' => $orderData['billingAddress']['province'] ?? '',
                'country' => $orderData['billingAddress']['country'] ?? '',
                'company' => $orderData['billingAddress']['company'] ?? '',
                'latitude' => $orderData['billingAddress']['latitude'] ?? '',
                'longitude' => $orderData['billingAddress']['longitude'] ?? '',
                'name' => $orderData['billingAddress']['name'] ?? '',
                'country_code' => $orderData['billingAddress']['countryCodeV2'] ?? '',
                'province_code' => $orderData['billingAddress']['provinceCode'] ?? '',
            ] : [];
            $orderResponse['shipping_address'] = isset($orderData['shippingAddress']) && is_array($orderData['shippingAddress']) ? [
                'id' => $orderData['shippingAddress']['id'] ?? '',
                'first_name' => $orderData['shippingAddress']['firstName'] ?? '',
                'last_name' => $orderData['shippingAddress']['lastName'] ?? '',
                'address1' => $orderData['shippingAddress']['address1'] ?? '',
                'address2' => $orderData['shippingAddress']['address2'] ?? '',
                'phone' => $orderData['shippingAddress']['phone'] ?? '',
                'city' => $orderData['shippingAddress']['city'] ?? '',
                'zip' => $orderData['shippingAddress']['zip'] ?? '',
                'province' => $orderData['shippingAddress']['province'] ?? '',
                'country' => $orderData['shippingAddress']['country'] ?? '',
                'company' => $orderData['shippingAddress']['company'] ?? '',
                'latitude' => $orderData['shippingAddress']['latitude'] ?? '',
                'longitude' => $orderData['shippingAddress']['longitude'] ?? '',
                'name' => $orderData['shippingAddress']['name'] ?? '',
                'country_code' => $orderData['shippingAddress']['countryCodeV2'] ?? '',
                'province_code' => $orderData['shippingAddress']['provinceCode'] ?? '',
            ] : [];
            $orderResponse['shipping_lines'] = isset($orderData['shippingLines']['edges']) && is_array($orderData['shippingLines']['edges']) ? array_map(function($item) {
                return [
                    'id' => $item['node']['id'] ?? '',
                    'title' => $item['node']['title'] ?? '',
                    'price' => $item['node']['originalPriceSet']['presentmentMoney']['amount'] ?? '',
                    'discount_allocations' => isset($item['node']['discountAllocations']) && is_array($item['node']['discountAllocations']) ? array_map(function($discount) {
                        return [
                            'amount' => $discount['allocatedAmountSet']['presentmentMoney']['amount'] ?? 0,
                        ];
                    }, $item['node']['discountAllocations']) : [],
                    'discounted_price' => isset($item['node']['discountedPriceSet']) && is_array($item['node']['discountedPriceSet']) ? array_map(function($discount) {
                        return [
                            'amount' => $discount['discountedPriceSet']['presentmentMoney']['amount'] ?? 0,
                        ];
                    }, $item['node']['discountedPriceSet']) : [],
                    'is_removed' => $item['node']['isRemoved'] ?? '',
                ];
            }, $orderData['shippingLines']['edges']) : [];}

            return $orderResponse;
        }

    }

    /**
     * To get Order Line Items by its Order Id use this function.
     */
    public function getOrderLineItems($param)
    {
        $orderId = $param['orderId'];
        $limit = isset($param['limit']) ? (int)$param['limit'] : 50;

        $lineItemsFields = implode("\n", $param['fields']);

        $allLineItems = [];
        $hasNextPage = true;
        $afterCursor = null;

        while ($hasNextPage) {
            $cursorParam = $afterCursor ? "after: \"{$afterCursor}\"" : '';
            $query = <<<QUERY
                query GetOrderLineItems {
                    order(id: "gid://shopify/Order/{$orderId}") {
                        lineItems(first: $limit $cursorParam) {
                            edges {
                                cursor
                                node {
                                    $lineItemsFields
                                }
                            }
                            pageInfo {
                                hasNextPage
                            }
                        }
                    }
                }
            QUERY;

            $response = $this->graphqlService->graphqlQueryThalia($query);

            if (isset($response['errors']) && !empty($response['errors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $response["errors"]);
            }

            $edges = $response['data']['order']['lineItems']['edges'] ?? [];

            foreach ($edges as $edge) {
                $node = $edge['node'];

                $properties = [];
                if (isset($node['customAttributes']) && is_array($node['customAttributes'])) {
                    foreach ($node['customAttributes'] as $attr) {
                        $properties[] = [
                            'name' => $attr['key'] ?? null,
                            'value' => $attr['value'] ?? null,
                        ];
                    }
                }

                $allLineItems[] = [
                    'id' => isset($node['id']) ? str_replace('gid://shopify/LineItem/', '', $node['id']) : '',
                    'admin_graphql_api_id' => $node['id'] ?? '',
                    'current_quantity' => $node['currentQuantity'] ?? '',
                    'fulfillment_status' => $node['fulfillmentStatus'] ?? '',
                    'name' => $node['name'] ?? '',
                    'product_id' => isset($node['product']['id']) ? str_replace('gid://shopify/Product/', '', $node['product']['id']) : '',
                    'quantity' => $node['quantity'] ?? '',
                    'requires_shipping' => $node['requiresShipping'] ?? '',
                    'sku' => $node['sku'] ?? '',
                    'taxable' => $node['taxable'] ?? '',
                    'title' => $node['title'] ?? '',
                    'properties' => $properties ?? [],
                    'price' => $node['originalUnitPriceSet']['presentmentMoney']['amount'] ?? '',
                    'total_discount_set' => $node['totalDiscountSet']['presentmentMoney']['amount'] ?? '',
                    'variant_id' => isset($node['variant']['id']) ? str_replace('gid://shopify/ProductVariant/', '', $node['variant']['id']) : '',
                    'variant_title' => $node['variant']['title'] ?? '',
                    'vendor' => $node['vendor'] ?? '',
                    'tax_lines' => $node['taxLines'] ?? [],
                    'discount_allocations' => isset($node['discountAllocations']) && is_array($node['discountAllocations']) ? array_map(function ($allocation) {
                        return [
                            'amount' => $allocation['allocatedAmountSet']['presentmentMoney']['amount'] ?? '',
                        ];
                    }, $node['discountAllocations']) : [],
                ];
            }

            $hasNextPage = $response['data']['order']['lineItems']['pageInfo']['hasNextPage'];
            $lastEdge = end($edges);
            $afterCursor = $lastEdge['cursor'] ?? null;
        }

        return $allLineItems;
    }

    /**
     * To get Orders Returns use this function.
     */
    public function getOrderReturns($param)
    {
        $orderId = $param['orderId'];
        $limit = isset($param['limit']) ? (int)$param['limit'] : 50;

        $returnsFields = implode("\n", $param['fields']);

        $allReturns = [];
        $hasNextPage = true;
        $afterCursor = null;

        while ($hasNextPage) {
            $cursorParam = $afterCursor ? "after: \"{$afterCursor}\"" : '';
            $query = <<<QUERY
                query GetOrderReturns {
                    order(id: "gid://shopify/Order/{$orderId}") {
                        returns(first: $limit $cursorParam) {
                            edges {
                                node {
                                    $returnsFields
                                }
                            }
                            pageInfo {
                                hasNextPage
                            }
                        }
                    }
                }
            QUERY;

            $response = $this->graphqlService->graphqlQueryThalia($query);

            if (isset($response['errors']) && !empty($response['errors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $response["errors"]);
            }

            $edges = $response['data']['order']['returns']['edges'] ?? [];

            foreach ($edges as $edge) {
                $node = $edge['node'];

                $reverseFulfillmentOrders = [];

                $rfoEdges = $node['reverseFulfillmentOrders']['edges'] ?? [];
                foreach ($rfoEdges as $rfoEdge) {
                    $rfoNode = $rfoEdge['node'];

                    $lineItems = [];

                    $liEdges = $rfoNode['lineItems']['edges'] ?? [];
                    foreach ($liEdges as $liEdge) {
                        $liNode = $liEdge['node'];
                        $lineItemData = $liNode['fulfillmentLineItem']['lineItem'] ?? [];

                        $lineItems[] = [
                            'id' => isset($lineItemData['id'])
                                ? str_replace('gid://shopify/LineItem/', '', $lineItemData['id'])
                                : '',
                            'name' => $lineItemData['name'] ?? '',
                            'product_id' => isset($lineItemData['product']['id'])
                                ? str_replace('gid://shopify/Product/', '', $lineItemData['product']['id'])
                                : '',
                            'quantity' => $lineItemData['quantity'] ?? 0,
                        ];
                    }

                    $reverseFulfillmentOrders[] = [
                        'lineItems' => $lineItems,
                    ];
                }
                
                $allReturns[] = [
                    'id' => isset($node['id'])
                        ? str_replace('gid://shopify/Return/', '', $node['id'])
                        : '',
                    'name' => $node['name'] ?? '',
                    'status' => $node['status'] ?? '',
                    'reverseFulfillmentOrders' => $reverseFulfillmentOrders,
                ];
            }

            $hasNextPage = $response['data']['order']['returns']['pageInfo']['hasNextPage'] ?? false;

            if ($hasNextPage && !empty($edges)) {
                $lastEdge = end($edges);
                $afterCursor = $lastEdge['cursor'] ?? null;
            } else {
                $afterCursor = null;
            }
        }

        return $allReturns;
    }

    /**
     * To get Order transection by its Order Id use this function.
     */
    public function transactionsForOrder($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/order?example=Retrieves+a+list+of+transactions&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/transaction#get-orders-order-id-transactions
        */

        $orderTransectionFields = implode("\n", $param['fields']);

        $orderTransactionVariable = [
            "orderId" => 'gid://shopify/Order/' . $param['orderId']
        ];

        $orderTransactionQuery = <<<QUERY
            query TransactionsForOrder(\$orderId: ID!) {
                order(id: \$orderId) {
                    transactions(first: 10) {
                        $orderTransectionFields
                    }
                }
            }
        QUERY;

        $responseData = $this->graphqlService->graphqlQueryThalia($orderTransactionQuery, $orderTransactionVariable);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $orderTransactionResponse = [];

            if (isset($responseData['data']['order']) && $responseData['data']['order'] != null) {
                foreach ($responseData['data']['order']['transactions'] as $response) {
                    $orderTransactionResponse[] = [
                        'id' => str_replace('gid://shopify/OrderTransaction/', '', $response['id']) ?? 0,
                        'order_id' => $response['order']['id'] ?? '',
                        'kind' => $response['kind'] ?? '',
                        'gateway' => $response['gateway'] ?? '',
                        'status' => $response['status'] ?? '',
                        'created_at' => $response['createdAt'] ?? '',
                        'test' => $response['test'] ?? '',
                        'authorization_code' => $response['authorizationCode'] ?? '',
                        'authorization_expires_at' => $response['authorizationExpiresAt'] ?? '',
                        'parent_id' => isset($response['parentTransaction']) ? $response['parentTransaction']['id'] : null,
                        'processed_at' => $response['processedAt'] ?? '',
                        'error_code' => $response['errorCode'] ?? '',
                        'receipt' => $response['receiptJson'] ?? '',
                        'fees' => $response['fees'] ?? '',
                        'amount' => $response['amountSet']['presentmentMoney']['amount'] ?? '',
                        'currency' => $response['amountSet']['presentmentMoney']['currencyCode'] ?? '',
                        'payment_id' => $response['paymentId'] ?? '',
                        'total_unsettled_set' => $response['totalUnsettledSet'] ?? '',
                        'admin_graphql_api_id' => $response['id'] ?? '',
                    ];
                }
            }

            return $orderTransactionResponse;

        }

    }

    /**
     * To get Order Count use this function.
     */
    public function OrdersCount($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/ordersCount?example=Retrieve+an+order+count
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/order#get-orders-count
        */

        $filters = [];

        if (!empty($param['query']['status'])) {
            $filters[] = "status:{$param['query']['status']}";
        }

        if (!empty($param['query']['created_at'])) {
            $filters[] = "created_at:{$param['query']['created_at']}";
        }

        if (!empty($param['query']['updated_at'])) {
            $filters[] = "updated_at:{$param['query']['updated_at']}";
        }

        if (!empty($param['query']['id'])) {
            $filters[] = "id:{$param['query']['id']}";
        }

        if (!empty($param['query']['name'])) {
            $filters[] = "name:{$param['query']['name']}";
        }

        if (!empty($param['query']['financial_status'])) {
            $filters[] = "financial_status:{$param['query']['financial_status']}";
        }

        if (!empty($param['query']['fulfillment_status'])) {
            $filters[] = "fulfillment_status:{$param['query']['fulfillment_status']}";
        }

        $queryString = !empty($filters) ? implode(" AND ", $filters) : "";

        $orderscountquery = <<<QUERY
        query OrdersCount {
            ordersCount(limit: 10000, query: "$queryString") {
                count
            }
        }
        QUERY;

        $responseData = $this->graphqlService->graphqlQueryThalia($orderscountquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            return $responseData['data']['ordersCount']['count'];
        }
    }

    /**
     * To get Order Risk use this function.
     */
    public function OrderRiskAssessmentsList($orderId)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/order?example=Retrieves+a+list+of+all+order+risks+for+an+order
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/order-risk#get-orders-order-id-risks
        */

        $orderriskquery = <<<'GRAPHQL'
        query OrderRiskAssessmentsList($orderId: ID!) {
            order(id: $orderId) {
                risk {
                    assessments {
                        riskLevel
                        provider {
                            title
                        }
                        facts {
                            description
                            sentiment
                        }
                    }
                    recommendation
                }
            }
        }
        GRAPHQL;

        $orderRiskVariables = ['orderId' => "gid://shopify/Order/{$orderId}"];

        $responseData = $this->graphqlService->graphqlQueryThalia($orderriskquery, $orderRiskVariables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $orderRiskData = $responseData['data']['order']['risk'];

            $riskresponse = array();

            foreach ($orderRiskData['assessments'] as $key => $orderRisk) {

                $risk = array();
                $risk['risk_level'] = $orderRisk['riskLevel'];
                $risk['provider'] = $orderRisk['provider']['title'] ?? '';
                $risk['facts'] = $orderRisk['facts'];
                $risk['recommendation'] = $orderRiskData['recommendation'];

                $riskresponse[] = $risk;

            }

            return $riskresponse;
        }
    }

    public function testquerFormmate()
    {
        $param = [
            'id' => 6341354586423,
            'query' => [
                'status' => 'any',
            ],
            'fields' => [
                'id',
                'cancelReason',
                'cancelledAt',
                'closedAt',
                'processedAt',
                'createdAt',
                'updatedAt',
                'currencyCode',
                'discountCodes',
                'displayFinancialStatus',
                'displayFulfillmentStatus',
                'name',
                'note',
                'confirmationNumber',
                'paymentGatewayNames',
                'phone',
                'tags',
                'email',
                'customer {
                    firstName
                    lastName
                    note
                    email
                    phone
                }',
                'taxLines {
                    title
                    price
                    rate
                    priceSet {
                        shopMoney {
                            amount
                            currencyCode
                        }
                        presentmentMoney {
                            amount
                            currencyCode
                        }
                    }
                }',
                'totalOutstandingSet {
                    presentmentMoney {
                        amount
                    }
                    shopMoney {
                        amount
                    }
                }',
                'totalPriceSet {
                    presentmentMoney {
                        amount
                    }
                    shopMoney {
                        amount
                    }
                }',
                'totalDiscountsSet {
                    presentmentMoney {
                        amount
                    }
                    shopMoney {
                        amount
                    }
                }',
                'customAttributes {
                    key
                    value
                }',
                'discountApplications(first: 10) {
                    edges {
                        node {
                            index
                            allocationMethod
                            targetSelection
                            targetType
                            value {
                                ... on MoneyV2 {
                                    amount
                                }
                                ... on PricingPercentageValue {
                                    percentage
                                }
                            }
                        }
                    }
                }',
                'fulfillments {
                    id
                    createdAt
                    name
                    order {
                        id
                    }
                    originAddress {
                        address1
                        address2
                        city
                        countryCode
                        provinceCode
                        zip
                    }
                    status
                    updatedAt
                    fulfillmentLineItems(first: 10) {
                        edges {
                            cursor
                            node {
                                id
                                quantity
                                originalTotalSet {
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                                lineItem {
                                    id
                                }
                            }
                        }
                    }
                }',
                'lineItems(first: 50) {
                    edges {
                        cursor
                        node {
                            id
                            currentQuantity
                            fulfillmentStatus
                            name
                            product {
                                id
                            }
                            quantity
                            requiresShipping
                            sku
                            taxable
                            title
                            customAttributes {
                                key
                                value
                            }
                            originalUnitPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            originalTotalSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            totalDiscountSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            variant {
                                id
                                title
                            }
                            vendor
                            taxLines {
                                title
                                price
                                rate
                                priceSet {
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                            }
                            discountAllocations {
                                allocatedAmountSet {
                                    presentmentMoney {
                                        amount
                                    }
                                    shopMoney {
                                        amount
                                    }
                                },
                            }
                        }
                    }
                }',
                'refunds {
                    id
                    createdAt
                    note
                    order {
                        id
                    }
                    orderAdjustments(first: 10) {
                        edges {
                            cursor
                            node {
                                amountSet {
                                    presentmentMoney {
                                        amount
                                    }
                                }
                                reason
                            }
                        }
                    }
                    refundLineItems(first: 10) {
                        edges {
                            cursor
                            node {
                                id
                                quantity
                                lineItem {
                                    id
                                }
                            }
                        }
                    }
                }',
                'billingAddress {
                    firstName
                    address1
                    phone
                    city
                    zip
                    province
                    country
                    lastName
                    address2
                    company
                    latitude
                    longitude
                    name
                    countryCodeV2
                    provinceCode
                }',
                'shippingAddress {
                    id
                    address1
                    address2
                    city
                    countryCodeV2
                    provinceCode
                    zip
                    name
                    phone
                    province
                    country
                    latitude
                    longitude
                }',
                'shippingLines(first: 10) {
                    edges {
                        cursor
                        node {
                            id
                            title
                            originalPriceSet {
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            discountAllocations {
                                allocatedAmountSet {
                                    presentmentMoney {
                                        amount
                                    }
                                    shopMoney {
                                        amount
                                    }
                                }
                            }
                            discountedPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            isRemoved
                        }
                    }
                }'
            ]
        ];
    }

    /**
     * To Order update use this function.
     */
    public function updateOrderTags($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/mutations/orderUpdate?example=Update+an+order
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/order#put-orders-order-id
        */

        global $graphqlService;

        $orderupdatequery = <<<'GRAPHQL'
        mutation OrderUpdate($input: OrderInput!) {
            orderUpdate(input: $input) {
                order {
                    cancelReason
                    cancelledAt
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $orderTagsVariables = [
            'input' => [
                'id' => "gid://shopify/Order/" . $params['order']['id'],
                'tags' => $params['order']['tags']
            ],
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($orderupdatequery, $orderTagsVariables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $orderUpdateData = $responseData;
        }

        return $orderUpdateData;
    }
}
