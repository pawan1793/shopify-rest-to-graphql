<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class FulfillmentsEndpoints
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
     * To get Locations use this  function.
     */
    public function fulfillmentOrderMove($fulfillmentOrderid, $params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/fulfillmentorder#post-fulfillment-orders-fulfillment-order-id-move
            Rest Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/fulfillmentOrderMove?example=Moves+a+fulfillment+order+to+a+new+location&language=PHP
        */



        $query = <<<'GRAPHQL'
                    mutation fulfillmentOrderMove($id: ID!, $newLocationId: ID!) {
                        fulfillmentOrderMove(id: $id, newLocationId: $newLocationId) {
                        movedFulfillmentOrder {
                            id
                            status
                        }
                        originalFulfillmentOrder {
                            id
                            status
                        }
                        remainingFulfillmentOrder {
                            id
                            status
                        }
                        userErrors {
                            field
                            message
                        }
                        }
                    }
                    GRAPHQL;


        $variables = [
            "id" => "gid://shopify/FulfillmentOrder/" . $fulfillmentOrderid,
            "newLocationId" => "gid://shopify/Location/" . $params['fulfillment_order']['new_location_id']
        ];




        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        }

        return $responseData;
    }

    public function createFulfillment($params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/mutations/fulfillmentcreate
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/fulfillment#post-fulfillments
        */



        $query = <<<'GRAPHQL'
                mutation FulfillmentCreate($fulfillment: FulfillmentInput!) {
                fulfillmentCreate(fulfillment: $fulfillment) {
                    fulfillment {
                    id
                    fulfillmentLineItems(first: 10) {
                        edges {
                        node {
                            id
                            lineItem {
                            title
                            variant {
                                id
                            }
                            }
                        }
                        }
                    }
                    status
                    trackingInfo(first: 10) {
                        company
                        number
                        url
                    }
                    }
                    userErrors {
                    field
                    message
                    }
                }
                }
                GRAPHQL;

        $param = $params['fulfillment']['line_items_by_fulfillment_order'][0];
        $param['fulfillment_order_id'] =str_replace("gid://shopify/Fulfillment/", "", $param['fulfillment_order_id']);

        $variables = [
            "fulfillment" => [
                "lineItemsByFulfillmentOrder" => [
                    "fulfillmentOrderId" => "gid://shopify/FulfillmentOrder/" . $param['fulfillment_order_id'],
                ],
                "notifyCustomer" => $params['fulfillment']['notify_customer'],
            ],
        ];
        if (isset($params['fulfillment']['tracking_info']) && !empty($params['fulfillment']['tracking_info'])) {
            $variables['fulfillment']['trackingInfo'] = [
                "company" => $params['fulfillment']['tracking_info']['company'] ?? '',
                "number" => $params['fulfillment']['tracking_info']['number'] ?? '',
                "url" => $params['fulfillment']['tracking_info']['url'] ?? '',
            ];
        }
        if (isset($param['fulfillment_order_line_items'][0]['id'])){
            $variables['fulfillment']['lineItemsByFulfillmentOrder']['fulfillmentOrderLineItems'] = [
                "id" =>  "gid://shopify/FulfillmentOrderLineItem/" . $param['fulfillment_order_line_items'][0]['id'] ?? '',
                "quantity" =>  $param['fulfillment_order_line_items'][0]['quantity'] ?? '',
            ];
        }

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            // throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $response = $responseData['data']['fulfillmentCreate']['fulfillment'];
            $response['id'] = isset($response['id']) ? str_replace('gid://shopify/FulfillmentLineItem/', '', $response['id']) : null;
        }

        return $response;
    }

    public function updateTracking($fulfillmentid, $params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/fulfillmentTrackingInfoUpdate?example=Updates+the+tracking+information+for+a+fulfillment
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/fulfillment#post-fulfillments-fulfillment-id-update-tracking
        */



        $query = <<<'GRAPHQL'
                mutation FulfillmentTrackingInfoUpdate($fulfillmentId: ID!, $trackingInfoInput: FulfillmentTrackingInput!, $notifyCustomer: Boolean) {
                    fulfillmentTrackingInfoUpdate(fulfillmentId: $fulfillmentId, trackingInfoInput: $trackingInfoInput, notifyCustomer: $notifyCustomer) {
                        fulfillment {
                        id
                        status
                        trackingInfo {
                            company
                            number
                            url
                        }
                        }
                        userErrors {
                        field
                        message
                        }
                    }
                }
                GRAPHQL;

        $fulfillmentid =str_replace("gid://shopify/Fulfillment/", "", $fulfillmentid);

        $variables = [
            "fulfillmentId" => "gid://shopify/Fulfillment/" . $fulfillmentid,
            "notifyCustomer" => $params['fulfillment']['notify_customer'],
            "trackingInfoInput" => [
                "company" => $params['fulfillment']['tracking_info']['company'],
                "number" => $params['fulfillment']['tracking_info']['number'],
                "url" => $params['fulfillment']['tracking_info']['url'],
            ],
        ];




        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $responseData['tracking_url'] = $responseData['data']['fulfillmentTrackingInfoUpdate']['fulfillment']['trackingInfo'][0]['url'];

        }



        return $responseData;
    }

    public function createFulfillmentEvent($fulfillmentid, $params)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/mutations/fulfillmentEventCreate?example=Creates+a+fulfillment+event&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/fulfillmentOrderMove?example=Moves+a+fulfillment+order+to+a+new+location&language=PHP
        */



        $query = <<<'GRAPHQL'
                mutation fulfillmentEventCreate($fulfillmentEvent: FulfillmentEventInput!) {
                    fulfillmentEventCreate(fulfillmentEvent: $fulfillmentEvent) {
                        fulfillmentEvent {
                        address1
                        city
                        country
                        estimatedDeliveryAt
                        happenedAt
                        latitude
                        longitude
                        message
                        province
                        status
                        zip
                        }
                        userErrors {
                        field
                        message
                        }
                    }
                }
                GRAPHQL;

        $fulfillmentid =str_replace("gid://shopify/Fulfillment/", "", $fulfillmentid);
        
        $variables = [
            "fulfillmentEvent" => [
                "fulfillmentId" => "gid://shopify/Fulfillment/" . $fulfillmentid,
                "status" => strtoupper($params['event']['status']),

            ],
        ];




        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));


        }


        return $responseData;
    }

    public function getFulfillmentOrder($orderid)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/queries/order?example=Retrieves+a+list+of+fulfillment+orders+for+a+specific+order
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/fulfillmentorder#get-orders-order-id-fulfillment-orders
        */



        $query = <<<'GRAPHQL'
     query FulfillmentOrderList($orderId: ID!) {
        order(id: $orderId) {
            fulfillmentOrders(first: 10) {
                nodes {
                    id
                    createdAt
                    updatedAt
                    requestStatus
                    status
                    fulfillAt
                    destination {
                        id
                        address1
                        address2
                        city
                        company
                        countryCode
                        email
                        firstName
                        lastName
                        phone
                        province
                        zip
                    }
                    lineItems(first: 250) {
                        nodes {
                            id
                            totalQuantity
                            inventoryItemId
                            remainingQuantity
                            variant {
                            id
                            }
                        }
                    }
                    internationalDuties {
                        incoterm
                    }
                    fulfillmentHolds {
                        reason
                        reasonNotes
                    }
                    fulfillBy
                    deliveryMethod {
                        id
                        methodType
                        minDeliveryDateTime
                        maxDeliveryDateTime
                        additionalInformation {
                            instructions
                            phone
                        }
                        serviceCode
                        sourceReference
                        presentedName
                        brandedPromise {
                            handle
                            name
                        }
                    }
                    assignedLocation {
                        address1
                        address2
                        city
                        countryCode
                        location {
                            id
                        }
                        name
                        phone
                        province
                        zip
                    }
                    merchantRequests(first: 250) {
                        nodes {
                            message
                            requestOptions
                            kind
                        }
                    }
                }
            }
        }
     }
    GRAPHQL;


        $variables = [
            "orderId" => "gid://shopify/Order/{$orderid}",
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            if(isset($responseData['data']['order']['fulfillmentOrders']['nodes'])) {
                $fulfillmentOrdersData = $responseData['data']['order']['fulfillmentOrders']['nodes'];

                $restResponse = array();
                foreach ($fulfillmentOrdersData as $key => $fulfillmentOrder) {
                    $restItem = [
                        "id" => str_replace("gid://shopify/FulfillmentOrder/", "", $fulfillmentOrder["id"]) ?? '',
                        "created_at" => $fulfillmentOrder["createdAt"] ?? '',
                        "updated_at" => $fulfillmentOrder["updatedAt"] ?? '',
                        "order_id" => $orderid ?? '',
                        "assigned_location_id" => str_replace("gid://shopify/Location/", "", $fulfillmentOrder["assignedLocation"]["location"]["id"]),
                        "request_status" => strtolower($fulfillmentOrder["requestStatus"]) ?? '',
                        "status" => strtolower($fulfillmentOrder["status"]) ?? '',
                        "fulfill_at" => $fulfillmentOrder["fulfillAt"] ?? '',
                        "fulfill_by" => $fulfillmentOrder["fulfillBy"] ?? '',
                        "destination" => $fulfillmentOrder["destination"] ?? [],
                        "line_items" => [],
                        "international_duties" => $fulfillmentOrder["internationalDuties"] ?? '',
                        "fulfillment_holds" => $fulfillmentOrder["fulfillmentHolds"] ?? '',
                        "assigned_location" => [
                            "address1" => $fulfillmentOrder["assignedLocation"]["address1"] ?? '',
                            "address2" => $fulfillmentOrder["assignedLocation"]["address2"] ?? '',
                            "city" => $fulfillmentOrder["assignedLocation"]["city"] ?? '',
                            "country_code" => $fulfillmentOrder["assignedLocation"]["countryCode"] ?? '',
                            "location_id" => str_replace("gid://shopify/Location/", "", $fulfillmentOrder["assignedLocation"]["location"]["id"]) ?? '',
                            "name" => $fulfillmentOrder["assignedLocation"]["name"] ?? '',
                            "phone" => $fulfillmentOrder["assignedLocation"]["phone"] ?? '',
                            "province" => $fulfillmentOrder["assignedLocation"]["province"] ?? '',
                            "zip" => $fulfillmentOrder["assignedLocation"]["zip"] ?? ''
                        ],
                        "merchant_requests" => []
                    ];

                    foreach ($fulfillmentOrder["lineItems"]["nodes"] as $lineItem) {
                        $restItem["line_items"][] = [
                            "id" => str_replace("gid://shopify/FulfillmentOrderLineItem/", "", $lineItem["id"]) ?? '',
                            "fulfillment_order_id" => $restItem["id"] ?? '',
                            "quantity" => $lineItem["totalQuantity"] ?? '',
                            "inventory_item_id" => str_replace("gid://shopify/InventoryItem/", "", $lineItem["inventoryItemId"]) ?? '',
                            "fulfillable_quantity" => $lineItem["remainingQuantity"] ?? '',
                            "variant_id" => isset($lineItem["variant"]["id"]) ? str_replace("gid://shopify/ProductVariant/", "", $lineItem["variant"]["id"]) : ''
                        ];
                    }

                    $restResponse[] = $restItem;
                }
            } else {
                return false;
            }

        }


        return $restResponse;
    }

}
