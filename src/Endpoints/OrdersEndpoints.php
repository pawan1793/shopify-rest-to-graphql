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
    public function getOrders()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/orders
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/order#get-orders
        */

        // Add it after email
        // customer {
        //     firstName
        //     lastName
        //     note
        // }



        $ordersquery = <<<'GRAPHQL'
            query {
                orders(first: 1) {
                    edges {
                        cursor
                        node {
                            id
                            cancelReason
                            cancelledAt
                            closedAt
                            processedAt
                            createdAt
                            updatedAt
                            currencyCode
                            discountCodes
                            displayFinancialStatus
                            displayFulfillmentStatus
                            name
                            note
                            confirmationNumber
                            paymentGatewayNames
                            phone
                            tags
                            email
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
                            totalOutstandingSet {
                                presentmentMoney {
                                    amount
                                }
                                shopMoney {
                                    amount
                                }
                            }
                            totalPriceSet {
                                presentmentMoney {
                                    amount
                                }
                                shopMoney {
                                    amount
                                }
                            }
                            totalDiscountsSet {
                                presentmentMoney {
                                    amount
                                }
                                shopMoney {
                                    amount
                                }
                            }
                            customAttributes {
                                key
                                value
                            }
                            discountApplications(first: 10) {
                                edges {
                                    node {
                                        index
                                        allocationMethod
                                        targetSelection
                                        targetType
                                        value
                                    }
                                }
                            }
                            fulfillments {
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
                            }
                            lineItems(first: 50) {
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
                                            discountApplication {
                                                allocationMethod
                                                targetSelection
                                                targetType
                                                value
                                            }
                                        }
                                    }
                                }
                            }
                            refunds {
                                id
                                createdAt
                                note
                                order {
                                    id
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
                            }
                            billingAddress {
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
                                countryCode
                                provinceCode
                            }
                            shippingAddress {
                                id
                                address1
                                address2
                                city
                                countryCode
                                provinceCode
                                zip
                                name
                                phone
                                province
                                country
                                latitude
                                longitude
                            }
                            shippingLines(first: 10) {
                                edges {
                                    cursor
                                    node {
                                        id
                                        title
                                        carrierIdentifier
                                        requestedFulfillmentService {
                                            id
                                        }
                                        shippingRateHandle
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
                                        price
                                        requestedFulfillmentService {
                                            id
                                        }
                                        shippingRateHandle
                                        title
                                    }
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($ordersquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $ordersData = $responseData['data']['orders']['edges'];

            $ordersResponse = array();

            foreach ($ordersData as $key => $orderData) {

                $orderResponse['id'] = str_replace('gid://shopify/Order/', '', $orderData['id']);
                $orderResponse['admin_graphql_api_id'] = $orderData['id'];
                $orderResponse['cancel_reason'] = $orderData['cancelReason'];
                $orderResponse['cancelledAt'] = $orderData['cancelledAt'];
                $orderResponse['closed_at'] = $orderData['closedAt'];
                $orderResponse['processed_at'] = $orderData['processedAt'];
                $orderResponse['created_at'] = $orderData['createdAt'];
                $orderResponse['updated_at'] = $orderData['updatedAt'];
                $orderResponse['currency'] = $orderData['currencyCode'];
                $orderResponse['discount_codes'] = $orderData['discountCodes'];
                $orderResponse['fulfillment_status'] = $orderData['displayFulfillmentStatus'];
                $orderResponse['financial_status'] = $orderData['displayFinancialStatus'];
                $orderResponse['name'] = $orderData['name'];
                $orderResponse['note'] = $orderData['note'];
                $orderResponse['confirmation_number'] = $orderData['confirmationNumber'];
                $orderResponse['payment_gateway_names'] = $orderData['paymentGatewayNames'];
                $orderResponse['phone'] = $orderData['phone'];
                $orderResponse['tags'] = $orderData['tags'];
                $orderResponse['email'] = $orderData['email'];
                // $orderResponse['customer'] = $orderData['customer'];
                $orderResponse['tax_lines'] = $orderData['taxLines'];
                $orderResponse['total_outstanding'] = $orderData['totalOutstandingSet']['presentmentMoney']['amount'];
                $orderResponse['total_price'] = $orderData['totalPriceSet']['presentmentMoney']['amount'];
                $orderResponse['total_discounts'] = $orderData['totalDiscountsSet']['presentmentMoney']['amount'];
                $orderResponse['note_attributes'] = $orderData['customAttributes'];
                $orderResponse['discount_applications'] = !empty($orderData['discountApplications']['edges']) ? $orderData['discountApplications']['edges']['node'] : '';
                $orderResponse['fulfillments'] = $orderData['fulfillments'];
                $orderResponse['line_items'] = array_map(function ($item) {
                    return [
                        'id' => str_replace('gid://shopify/LineItem/', '', $item['node']['id']),
                        'admin_graphql_api_id' => $item['node']['id'],
                        'current_quantity' => $item['node']['currentQuantity'],
                        'fulfillment_status' => $item['node']['fulfillmentStatus'],
                        'name' => $item['node']['name'],
                        'product_id' => str_replace('gid://shopify/Product/', '', $item['node']['product']['id']),
                        'quantity' => $item['node']['quantity'],
                        'requires_shipping' => $item['node']['requiresShipping'],
                        'sku' => $item['node']['sku'],
                        'taxable' => $item['node']['taxable'],
                        'title' => $item['node']['title'],
                        'total_discount_set' => $item['node']['totalDiscountSet'],
                        'variant_id' => str_replace('gid://shopify/ProductVariant/', '', $item['node']['variant']['id']),
                        'variant_title' => $item['node']['variant']['title'],
                        'vendor' => $item['node']['vendor'],
                        'tax_lines' => $item['node']['taxLines'],
                        'discount_allocations' => $item['node']['discountAllocations']
                    ];
                }, $orderData['lineItems']['edges']);
                $orderResponse['refunds'] = array_map(function ($refund) {
                    return [
                        'id' => str_replace('gid://shopify/Refund/', '', $refund['id']),
                        'admin_graphql_api_id' => $refund['id'],
                        'created_at' => $refund['createdAt'],
                        'note' => $refund['note'],
                        'order_id' => str_replace('gid://shopify/Order/', '', $refund['order']['id']),
                        'refund_line_items' => array_map(function ($refundLineItem) {
                            return [
                                'id' => str_replace('gid://shopify/RefundLineItem/', '', $refundLineItem['node']['id']),
                                'line_item_id' => str_replace('gid://shopify/LineItem/', '', $refundLineItem['node']['lineItem']['id']),
                                'quantity' => $refundLineItem['node']['quantity']
                            ];
                        }, $refund['refundLineItems']['edges'])
                    ];
                }, $orderData['refunds']);
                $orderResponse['billing_address'] = $orderData['billingAddress'];
                $orderResponse['shipping_address'] = $orderData['shippingAddress'];
                $orderResponse['shipping_lines'] = !empty($orderData['shippingLines']['edges']) ? $orderData['shippingLines']['edges']['node'] : '';

            }

            return $ordersResponse;

        }

    }

    /** 
     * To get Order by its ID use this function.
     */
    public function getOrder($orderId)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/order?example=Retrieve+a+specific+order
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/order#get-orders-order-id
        */




        // Add it after email
        // customer {
        //     firstName
        //     lastName
        //     note
        // }

        $orderquery = <<<'GRAPHQL'
            query GetOrderById($id: ID!) {
                order(id: $id) {
                    id
                    cancelReason
                    cancelledAt
                    closedAt
                    processedAt
                    createdAt
                    updatedAt
                    currencyCode
                    discountCodes
                    displayFinancialStatus
                    displayFulfillmentStatus
                    name
                    note
                    confirmationNumber
                    paymentGatewayNames
                    phone
                    tags
                    email
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
                    totalOutstandingSet {
                        presentmentMoney {
                            amount
                        }
                        shopMoney {
                            amount
                        }
                    }
                    totalPriceSet {
                        presentmentMoney {
                            amount
                        }
                        shopMoney {
                            amount
                        }
                    }
                    totalDiscountsSet {
                        presentmentMoney {
                            amount
                        }
                        shopMoney {
                            amount
                        }
                    }
                    customAttributes {
                        key
                        value
                    }
                    discountApplications(first: 10) {
                        edges {
                            node {
                                index
                                allocationMethod
                                targetSelection
                                targetType
                                value
                            }
                        }
                    }
                    fulfillments {
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
                    }
                    lineItems(first: 50) {
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
                                    discountApplication {
                                        allocationMethod
                                        targetSelection
                                        targetType
                                        value
                                    }
                                }
                            }
                        }
                    }
                    refunds {
                        id
                        createdAt
                        note
                        order {
                            id
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
                    }
                    billingAddress {
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
                        countryCode
                        provinceCode
                    }
                    shippingAddress {
                        id
                        address1
                        address2
                        city
                        countryCode
                        provinceCode
                        zip
                        name
                        phone
                        province
                        country
                        latitude
                        longitude
                    }
                    shippingLines(first: 10) {
                        edges {
                            cursor
                            node {
                                id
                                title
                                carrierIdentifier
                                requestedFulfillmentService {
                                    id
                                }
                                shippingRateHandle
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
                                price
                                requestedFulfillmentService {
                                    id
                                }
                                shippingRateHandle
                                title
                            }
                        }
                    }
                }
            }
            GRAPHQL;

        $ordervariables = ['id' => "gid://shopify/Order/{$orderId}"];

        $responseData = $this->graphqlService->graphqlQueryThalia($orderquery, $ordervariables);
        // echo '<pre>';
        // print_r($responseData['data']['order']);
        // echo '</pre>';
        // exit();

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $orderData = $responseData['data']['order'];

            $orderResponse = array();

            $orderResponse['id'] = str_replace('gid://shopify/Order/', '', $orderData['id']);
            $orderResponse['admin_graphql_api_id'] = $orderData['id'];
            $orderResponse['cancel_reason'] = $orderData['cancelReason'];
            $orderResponse['cancelledAt'] = $orderData['cancelledAt'];
            $orderResponse['closed_at'] = $orderData['closedAt'];
            $orderResponse['processed_at'] = $orderData['processedAt'];
            $orderResponse['created_at'] = $orderData['createdAt'];
            $orderResponse['updated_at'] = $orderData['updatedAt'];
            $orderResponse['currency'] = $orderData['currencyCode'];
            $orderResponse['discount_codes'] = $orderData['discountCodes'];
            $orderResponse['fulfillment_status'] = $orderData['displayFulfillmentStatus'];
            $orderResponse['financial_status'] = $orderData['displayFinancialStatus'];
            $orderResponse['name'] = $orderData['name'];
            $orderResponse['note'] = $orderData['note'];
            $orderResponse['confirmation_number'] = $orderData['confirmationNumber'];
            $orderResponse['payment_gateway_names'] = $orderData['paymentGatewayNames'];
            $orderResponse['phone'] = $orderData['phone'];
            $orderResponse['tags'] = $orderData['tags'];
            $orderResponse['email'] = $orderData['email'];
            // $orderResponse['customer'] = $orderData['customer'];
            $orderResponse['tax_lines'] = $orderData['taxLines'];
            $orderResponse['total_outstanding'] = $orderData['totalOutstandingSet']['presentmentMoney']['amount'];
            $orderResponse['total_price'] = $orderData['totalPriceSet']['presentmentMoney']['amount'];
            $orderResponse['total_discounts'] = $orderData['totalDiscountsSet']['presentmentMoney']['amount'];
            $orderResponse['note_attributes'] = $orderData['customAttributes'];
            $orderResponse['discount_applications'] = !empty($orderData['discountApplications']['edges']) ? $orderData['discountApplications']['edges']['node'] : '';
            $orderResponse['fulfillments'] = $orderData['fulfillments'];
            $orderResponse['line_items'] = array_map(function ($item) {
                return [
                    'id' => str_replace('gid://shopify/LineItem/', '', $item['node']['id']),
                    'admin_graphql_api_id' => $item['node']['id'],
                    'current_quantity' => $item['node']['currentQuantity'],
                    'fulfillment_status' => $item['node']['fulfillmentStatus'],
                    'name' => $item['node']['name'],
                    'product_id' => str_replace('gid://shopify/Product/', '', $item['node']['product']['id']),
                    'quantity' => $item['node']['quantity'],
                    'requires_shipping' => $item['node']['requiresShipping'],
                    'sku' => $item['node']['sku'],
                    'taxable' => $item['node']['taxable'],
                    'title' => $item['node']['title'],
                    'total_discount_set' => $item['node']['totalDiscountSet'],
                    'variant_id' => str_replace('gid://shopify/ProductVariant/', '', $item['node']['variant']['id']),
                    'variant_title' => $item['node']['variant']['title'],
                    'vendor' => $item['node']['vendor'],
                    'tax_lines' => $item['node']['taxLines'],
                    'discount_allocations' => $item['node']['discountAllocations']
                ];
            }, $orderData['lineItems']['edges']);
            $orderResponse['refunds'] = array_map(function ($refund) {
                return [
                    'id' => str_replace('gid://shopify/Refund/', '', $refund['id']),
                    'admin_graphql_api_id' => $refund['id'],
                    'created_at' => $refund['createdAt'],
                    'note' => $refund['note'],
                    'order_id' => str_replace('gid://shopify/Order/', '', $refund['order']['id']),
                    'refund_line_items' => array_map(function ($refundLineItem) {
                        return [
                            'id' => str_replace('gid://shopify/RefundLineItem/', '', $refundLineItem['node']['id']),
                            'line_item_id' => str_replace('gid://shopify/LineItem/', '', $refundLineItem['node']['lineItem']['id']),
                            'quantity' => $refundLineItem['node']['quantity']
                        ];
                    }, $refund['refundLineItems']['edges'])
                ];
            }, $orderData['refunds']);
            $orderResponse['billing_address'] = $orderData['billingAddress'];
            $orderResponse['shipping_address'] = $orderData['shippingAddress'];
            $orderResponse['shipping_lines'] = !empty($orderData['shippingLines']['edges']) ? $orderData['shippingLines']['edges']['node'] : '';

            return $orderResponse;
        }

    }

    /** 
     * To get Order transection by its Order Id use this function.
     */
    public function transactionsForOrder($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/order?example=Retrieves+a+list+of+transactions&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/transaction#get-orders-order-id-transactions
        */



        $orderTransactionVariable = [
            "orderId" => 'gid://shopify/Order/' . $param['orderId']
        ];

        $orderTransactionQuery = <<<'GRAPHQL'
            query TransactionsForOrder($orderId: ID!) {
                order(id: $orderId) {
                    transactions(first: 10) {
                        id
                        order {
                            id
                        }
                        kind
                        gateway
                        status
                        createdAt
                        test
                        authorizationCode
                        authorizationExpiresAt
                        parentTransaction {
                        id
                        }
                        processedAt
                        errorCode
                        receiptJson
                        fees {
                            amount {
                                amount
                                currencyCode
                            }
                        }
                        amountSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        paymentDetails {
                            ... on CardPaymentDetails {
                                paymentMethodName
                            }
                            ... on ShopPayInstallmentsPaymentDetails {
                                paymentMethodName
                            }
                        }
                        paymentIcon {
                            url
                        }
                        paymentId
                        totalUnsettledSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        formattedGateway
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($orderTransactionQuery, $orderTransactionVariable);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $orderTransactionResponse = [];

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

            return $orderTransactionResponse;

        }

    }

    /** 
     * To get Order Count use this function.
     */
    public function OrdersCount()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/ordersCount?example=Retrieve+an+order+count
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/order#get-orders-count
        */



        $orderscountquery = <<<'GRAPHQL'
            query OrdersCount {
                ordersCount(limit: 10000) {
                    count
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($orderscountquery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

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
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/order?example=Retrieves+a+list+of+all+order+risks+for+an+order
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/order-risk#get-orders-order-id-risks
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

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

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


}
