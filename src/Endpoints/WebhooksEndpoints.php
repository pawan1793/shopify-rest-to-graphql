<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class WebhooksEndpoints
{

    const WebhookTopics = [
        'app_purchases_one_time/update' => 'APP_PURCHASES_ONE_TIME_UPDATE',
        'app/scopes_update' => 'APP_SCOPES_UPDATE',
        'app_subscriptions/approaching_capped_amount' => 'APP_SUBSCRIPTIONS_APPROACHING_CAPPED_AMOUNT',
        'app_subscriptions/update' => 'APP_SUBSCRIPTIONS_UPDATE',
        'app/uninstalled' => 'APP_UNINSTALLED',
        'audit_events/admin_api_activity' => 'AUDIT_EVENTS_ADMIN_API_ACTIVITY',
        'bulk_operations/finish' => 'BULK_OPERATIONS_FINISH',
        'carts/create' => 'CARTS_CREATE',
        'carts/update' => 'CARTS_UPDATE',
        'channels/delete' => 'CHANNELS_DELETE',
        'checkouts/create' => 'CHECKOUTS_CREATE',
        'checkouts/delete' => 'CHECKOUTS_DELETE',
        'checkouts/update' => 'CHECKOUTS_UPDATE',
        'collections/create' => 'COLLECTIONS_CREATE',
        'collections/delete' => 'COLLECTIONS_DELETE',
        "collections/update" => "COLLECTIONS_UPDATE",
        "collection_listings/add" => "COLLECTION_LISTINGS_ADD",
        "collection_listings/remove" => "COLLECTION_LISTINGS_REMOVE",
        "collection_listings/update" => "COLLECTION_LISTINGS_UPDATE",
        "collection_publications/create" => "COLLECTION_PUBLICATIONS_CREATE",
        "collection_publications/delete" => "COLLECTION_PUBLICATIONS_DELETE",
        "collection_publications/update" => "COLLECTION_PUBLICATIONS_UPDATE",
        "companies/create" => "COMPANIES_CREATE",
        "companies/delete" => "COMPANIES_DELETE",
        "companies/update" => "COMPANIES_UPDATE",
        "company_contacts/create" => "COMPANY_CONTACTS_CREATE",
        "company_contacts/delete" => "COMPANY_CONTACTS_DELETE",
        "company_contacts/update" => "COMPANY_CONTACTS_UPDATE",
        "company_contact_roles/assign" => "COMPANY_CONTACT_ROLES_ASSIGN",
        "company_contact_roles/revoke" => "COMPANY_CONTACT_ROLES_REVOKE",
        "company_locations/create" => "COMPANY_LOCATIONS_CREATE",
        "company_locations/delete" => "COMPANY_LOCATIONS_DELETE",
        "company_locations/update" => "COMPANY_LOCATIONS_UPDATE",
        "customers/create" => "CUSTOMERS_CREATE",
        "customers/delete" => "CUSTOMERS_DELETE",
        "customers/disable" => "CUSTOMERS_DISABLE",
        "customers_email_marketing_consent/update" => "CUSTOMERS_EMAIL_MARKETING_CONSENT_UPDATE",
        "customers/enable" => "CUSTOMERS_ENABLE",
        "customers_marketing_consent/update" => "CUSTOMERS_MARKETING_CONSENT_UPDATE",
        "customers/merge" => "CUSTOMERS_MERGE",
        "customers/purchasing_summary" => "CUSTOMERS_PURCHASING_SUMMARY",
        "customers/update" => "CUSTOMERS_UPDATE",
        "customer_account_settings/update" => "CUSTOMER_ACCOUNT_SETTINGS_UPDATE",
        "customer_groups/create" => "CUSTOMER_GROUPS_CREATE",
        "customer_groups/delete" => "CUSTOMER_GROUPS_DELETE",
        "customer_groups/update" => "CUSTOMER_GROUPS_UPDATE",
        "customer.joined_segment" => "CUSTOMER_JOINED_SEGMENT",
        "customer.left_segment" => "CUSTOMER_LEFT_SEGMENT",
        "customer_payment_methods/create" => "CUSTOMER_PAYMENT_METHODS_CREATE",
        "customer_payment_methods/revoke" => "CUSTOMER_PAYMENT_METHODS_REVOKE",
        "customer_payment_methods/update" => "CUSTOMER_PAYMENT_METHODS_UPDATE",
        "customer.tags_added" => "CUSTOMER_TAGS_ADDED",
        "customer.tags_removed" => "CUSTOMER_TAGS_REMOVED",
        "delivery_promise_settings/update" => "DELIVERY_PROMISE_SETTINGS_UPDATE",
        "discounts/create" => "DISCOUNTS_CREATE",
        "discounts/delete" => "DISCOUNTS_DELETE",
        "discounts/redeemcode_added" => "DISCOUNTS_REDEEMCODE_ADDED",
        "discounts/redeemcode_removed" => "DISCOUNTS_REDEEMCODE_REMOVED",
        "discounts/update" => "DISCOUNTS_UPDATE",
        "disputes/create" => "DISPUTES_CREATE",
        "disputes/update" => "DISPUTES_UPDATE",
        "domains/create" => "DOMAINS_CREATE",
        "domains/destroy" => "DOMAINS_DESTROY",
        "domains/update" => "DOMAINS_UPDATE",
        "draft_orders/create" => "DRAFT_ORDERS_CREATE",
        "draft_orders/delete" => "DRAFT_ORDERS_DELETE",
        "draft_orders/update" => "DRAFT_ORDERS_UPDATE",
        "fulfillments/create" => "FULFILLMENTS_CREATE",
        "fulfillments/update" => "FULFILLMENTS_UPDATE",
        "fulfillment_events/create" => "FULFILLMENT_EVENTS_CREATE",
        "fulfillment_events/delete" => "FULFILLMENT_EVENTS_DELETE",
        "fulfillment_holds/added" => "FULFILLMENT_HOLDS_ADDED",
        "fulfillment_holds/released" => "FULFILLMENT_HOLDS_RELEASED",
        "fulfillment_orders/cancellation_request_accepted" => "FULFILLMENT_ORDERS_CANCELLATION_REQUEST_ACCEPTED",
        "fulfillment_orders/cancellation_request_rejected" => "FULFILLMENT_ORDERS_CANCELLATION_REQUEST_REJECTED",
        "fulfillment_orders/cancellation_request_submitted" => "FULFILLMENT_ORDERS_CANCELLATION_REQUEST_SUBMITTED",
        "fulfillment_orders/cancelled" => "FULFILLMENT_ORDERS_CANCELLED",
        "fulfillment_orders/fulfillment_request_accepted" => "FULFILLMENT_ORDERS_FULFILLMENT_REQUEST_ACCEPTED",
        "fulfillment_orders/fulfillment_request_rejected" => "FULFILLMENT_ORDERS_FULFILLMENT_REQUEST_REJECTED",
        "fulfillment_orders/fulfillment_request_submitted" => "FULFILLMENT_ORDERS_FULFILLMENT_REQUEST_SUBMITTED",
        "fulfillment_orders/fulfillment_service_failed_to_complete" => "FULFILLMENT_ORDERS_FULFILLMENT_SERVICE_FAILED_TO_COMPLETE",
        "fulfillment_orders/hold_released" => "FULFILLMENT_ORDERS_HOLD_RELEASED",
        "fulfillment_orders/in_progress" => "FULFILLMENT_ORDERS_IN_PROGRESS",
        "fulfillment_orders/rejected" => "FULFILLMENT_ORDERS_REJECTED",
        "fulfillment_orders/rescheduled" => "FULFILLMENT_ORDERS_RESCHEDULED",
        "inventory_items/create" => "INVENTORY_ITEMS_CREATE",
        "inventory_items/delete" => "INVENTORY_ITEMS_DELETE",
        "inventory_items/update" => "INVENTORY_ITEMS_UPDATE",
        "inventory_levels/connect" => "INVENTORY_LEVELS_CONNECT",
        "inventory_levels/disconnect" => "INVENTORY_LEVELS_DISCONNECT",
        "inventory_levels/update" => "INVENTORY_LEVELS_UPDATE",
        "locations/create" => "LOCATIONS_CREATE",
        "locations/delete" => "LOCATIONS_DELETE",
        "locations/update" => "LOCATIONS_UPDATE",
        "orders/cancelled" => "ORDERS_CANCELLED",
        "orders/create" => "ORDERS_CREATE",
        "orders/fulfilled" => "ORDERS_FULFILLED",
        "orders/paid" => "ORDERS_PAID",
        "orders/partially_fulfilled" => "ORDERS_PARTIALLY_FULFILLED",
        "orders/updated" => "ORDERS_UPDATED",
        "orders/edited" => "ORDERS_EDITED",
        "products/create" => "PRODUCTS_CREATE",
        "products/delete" => "PRODUCTS_DELETE",
        "products/update" => "PRODUCTS_UPDATE",
        "refunds/create" => "REFUNDS_CREATE",
        "shop/update" => "SHOP_UPDATE",
        "subscriptions/create" => "SUBSCRIPTIONS_CREATE",
        "subscriptions/updated" => "SUBSCRIPTIONS_UPDATED",
        "tax_services/update" => "TAX_SERVICES_UPDATE",
        "transactions/create" => "TRANSACTIONS_CREATE",
        "returns/approve" => "RETURNS_APPROVE",
        "returns/cancel" => "RETURNS_CANCEL",
        "returns/close" => "RETURNS_CLOSE",
        "returns/decline" => "RETURNS_DECLINE",
        "returns/reopen" => "RETURNS_REOPEN",
        "returns/request" => "RETURNS_REQUEST",
        "returns/update" => "RETURNS_UPDATE",
        "reverse_deliveries/attach_deliverable" => "REVERSE_DELIVERIES_ATTACH_DELIVERABLE",
        "reverse_fulfillment_orders/dispose" => "REVERSE_FULFILLMENT_ORDERS_DISPOSE",
        "scheduled_product_listings/add" => "SCHEDULED_PRODUCT_LISTINGS_ADD",
        "scheduled_product_listings/remove" => "SCHEDULED_PRODUCT_LISTINGS_REMOVE",
        "scheduled_product_listings/update" => "SCHEDULED_PRODUCT_LISTINGS_UPDATE",
        "segments/create" => "SEGMENTS_CREATE",
        "segments/delete" => "SEGMENTS_DELETE",
        "segments/update" => "SEGMENTS_UPDATE",
        "selling_plan_groups/create" => "SELLING_PLAN_GROUPS_CREATE",
        "selling_plan_groups/delete" => "SELLING_PLAN_GROUPS_DELETE",
        "selling_plan_groups/update" => "SELLING_PLAN_GROUPS_UPDATE",
        "shipping_addresses/create" => "SHIPPING_ADDRESSES_CREATE",
        "shipping_addresses/update" => "SHIPPING_ADDRESSES_UPDATE",
        "subscription_billing_attempts/challenged" => "SUBSCRIPTION_BILLING_ATTEMPTS_CHALLENGED",
        "subscription_billing_attempts/failure" => "SUBSCRIPTION_BILLING_ATTEMPTS_FAILURE",
        "subscription_billing_attempts/success" => "SUBSCRIPTION_BILLING_ATTEMPTS_SUCCESS",
        "subscription_billing_cycles/skip" => "SUBSCRIPTION_BILLING_CYCLES_SKIP",
        "subscription_billing_cycles/unskip" => "SUBSCRIPTION_BILLING_CYCLES_UNSKIP",
        "subscription_billing_cycle_edits/create" => "SUBSCRIPTION_BILLING_CYCLE_EDITS_CREATE",
        "subscription_billing_cycle_edits/delete" => "SUBSCRIPTION_BILLING_CYCLE_EDITS_DELETE",
        "subscription_billing_cycle_edits/update" => "SUBSCRIPTION_BILLING_CYCLE_EDITS_UPDATE",
        "subscription_contracts/activate" => "SUBSCRIPTION_CONTRACTS_ACTIVATE",
        "subscription_contracts/cancel" => "SUBSCRIPTION_CONTRACTS_CANCEL",
        "subscription_contracts/create" => "SUBSCRIPTION_CONTRACTS_CREATE",
        "subscription_contracts/expire" => "SUBSCRIPTION_CONTRACTS_EXPIRE",
        "subscription_contracts/fail" => "SUBSCRIPTION_CONTRACTS_FAIL",
        "subscription_contracts/pause" => "SUBSCRIPTION_CONTRACTS_PAUSE",
        "subscription_contracts/update" => "SUBSCRIPTION_CONTRACTS_UPDATE",
        "tax_services/create" => "TAX_SERVICES_CREATE",
        "tender_transactions/create" => "TENDER_TRANSACTIONS_CREATE",
        "themes/create" => "THEMES_CREATE",
        "themes/delete" => "THEMES_DELETE",
        "themes/publish" => "THEMES_PUBLISH",
        "themes/update" => "THEMES_UPDATE",
        "variants/in_stock" => "VARIANTS_IN_STOCK",
        "variants/out_of_stock" => "VARIANTS_OUT_OF_STOCK"
    ];

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
     * To get all webook subscriptions use this function.
     */
    public function webhookSubscriptions()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/webhookSubscriptions
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/webhook#get-webhooks
        */



        $webhookQuery = <<<"GRAPHQL"
        query {
            webhookSubscriptions(first: 250) {
                edges {
                    node {
                        id
                        topic
                        createdAt
                        updatedAt
                        format
                        endpoint {
                            __typename
                            ... on WebhookHttpEndpoint {
                                callbackUrl
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($webhookQuery);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $webhookSubscriptionsResponse = [];

            foreach ($responseData['data']['webhookSubscriptions']['edges'] as $key => $response) {
                $webhookSubscriptionsResponse[$key]['id'] = str_replace('gid://shopify/WebhookSubscription/', '', $response['node']['id']);
                $webhookSubscriptionsResponse[$key]['address'] = $response['node']['endpoint']['callbackUrl'] ?? '';
                $webhookSubscriptionsResponse[$key]['topic'] = array_flip(self::WebhookTopics)[$response['node']['topic']] ?? '';
                $webhookSubscriptionsResponse[$key]['createdAt'] = $response['node']['createdAt'] ?? '';
                $webhookSubscriptionsResponse[$key]['updatedAt'] = $response['node']['updatedAt'] ?? '';
                $webhookSubscriptionsResponse[$key]['format'] = $response['node']['format'];
            }

            return $webhookSubscriptionsResponse;

        }

    }

    /** 
     * To create a webhook subscription use this function.
     */
    public function webhookSubscriptionCreate($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/webhookSubscriptionCreate
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/webhook#post-webhooks
        */


        $webhookParams = $param['webhook'];

        if(isset(self::WebhookTopics[$webhookParams['topic']])){
            $webhookParams['topic'] = self::WebhookTopics[$webhookParams['topic']];
        }

        if($webhookParams['format'] == 'json'){
            $webhookParams['format'] = 'JSON';
        }

        $webhookCreationVariable = [
            'topic' => $webhookParams['topic'],
            'webhookSubscription' => [
                'callbackUrl' => $webhookParams['address'],
                'format' => $webhookParams['format'],
            ]
        ];

        $webhookQuery = <<<'GRAPHQL'
        mutation WebhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                webhookSubscription {
                    id
                    topic
                    createdAt
                    updatedAt
                    format
                    endpoint {
                        __typename
                        ... on WebhookHttpEndpoint {
                            callbackUrl
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($webhookQuery, $webhookCreationVariable);

        if (isset($responseData['data']['webhookSubscriptionCreate']['userErrors']) && !empty($responseData['data']['webhookSubscriptionCreate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['webhookSubscriptionCreate']['userErrors']);

        } else {

            $webhookSubscriptionsCreateResponse = [];

            if (!empty($responseData['data']['webhookSubscriptionCreate']['webhookSubscription'])) {
                $response = $responseData['data']['webhookSubscriptionCreate']['webhookSubscription'];

                $webhookSubscriptionsCreateResponse['id'] = str_replace("gid://shopify/WebhookSubscription/", "", $response['id']) ?? '';
                
                $response['topic'] = array_flip(self::WebhookTopics)[$response['topic']] ?? $response['topic'];

                $webhookSubscriptionsCreateResponse['topic'] = $response['topic'];
                $webhookSubscriptionsCreateResponse['created_at'] = $response['createdAt'] ?? '';
                $webhookSubscriptionsCreateResponse['updated_at'] = $response['updatedAt'] ?? '';
                if($response['format'] == 'JSON'){
                    $response['format'] = 'json';
                }
                $webhookSubscriptionsCreateResponse['format'] = $response['format'];
                $webhookSubscriptionsCreateResponse['address'] = $response['endpoint']['callbackUrl'] ?? '';
            }

            return $webhookSubscriptionsCreateResponse;

        }

    }

    /** 
     * To update a webhook subscription use this function.
     */
    public function webhookSubscriptionUpdate($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/webhookSubscriptionUpdate
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/webhook#put-webhooks-webhook-id
        */


        $webhookParams = $param['webhook'];

        $webhookUpdateVariable = [
            'id' => 'gid://shopify/WebhookSubscription/' . $webhookParams['id'],
            'webhookSubscription' => [
                'callbackUrl' => $webhookParams['address'],
            ]
        ];

        $webhookQuery = <<<'GRAPHQL'
        mutation WebhookSubscriptionUpdate($id: ID!, $webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionUpdate(id: $id, webhookSubscription: $webhookSubscription) {
                webhookSubscription {
                    id
                    topic
                    createdAt
                    updatedAt
                    format
                    endpoint {
                        __typename
                        ... on WebhookHttpEndpoint {
                            callbackUrl
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($webhookQuery, $webhookUpdateVariable);

        if (isset($responseData['data']['webhookSubscriptionUpdate']['userErrors']) && !empty($responseData['data']['webhookSubscriptionUpdate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['webhookSubscriptionUpdate']['userErrors']);

        } else {

            $webhookSubscriptionsUpdateResponse = [];

            if (!empty($responseData['data']['webhookSubscriptionUpdate']['webhookSubscription'])) {
                $response = $responseData['data']['webhookSubscriptionUpdate']['webhookSubscription'];

                $webhookSubscriptionsUpdateResponse['id'] = str_replace("gid://shopify/WebhookSubscription/", "", $response['id']) ?? '';
                
                $response['topic'] = array_flip(self::WebhookTopics)[$response['topic']] ?? $response['topic'];

                $webhookSubscriptionsUpdateResponse['topic'] = $response['topic'];
                $webhookSubscriptionsUpdateResponse['created_at'] = $response['createdAt'] ?? '';
                $webhookSubscriptionsUpdateResponse['updated_at'] = $response['updatedAt'] ?? '';
                if($response['format'] == 'JSON'){
                    $response['format'] = 'json';
                }
                $webhookSubscriptionsUpdateResponse['format'] = $response['format'];
                $webhookSubscriptionsUpdateResponse['address'] = $response['endpoint']['callbackUrl'] ?? '';
            }

            return $webhookSubscriptionsUpdateResponse;

        }

    }

    /** 
     * To delete a webhook subscription use this function.
     */
    public function webhookSubscriptionDelete($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/webhookSubscriptionDelete
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/webhook#delete-webhooks-webhook-id
        */



        $webhookDeleteVariable = [
            'id' => 'gid://shopify/WebhookSubscription/' . $param['id'],
        ];

        $webhookQuery = <<<'GRAPHQL'
        mutation webhookSubscriptionDelete($id: ID!) {
            webhookSubscriptionDelete(id: $id) {
                deletedWebhookSubscriptionId
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($webhookQuery, $webhookDeleteVariable);
        

        if (isset($responseData['data']['webhookSubscriptionDelete']['userErrors']) && !empty($responseData['data']['webhookSubscriptionDelete']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['webhookSubscriptionDelete']['userErrors']);

        } else {

            $responseData = $responseData['data']['webhookSubscriptionDelete'];
            $responseData['id'] = str_replace('gid://shopify/WebhookSubscription/', '', $responseData['deletedWebhookSubscriptionId']);

        }

        return $responseData;
    }

}
