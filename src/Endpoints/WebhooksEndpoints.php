<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;

class WebhooksEndpoints
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

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $webhookSubscriptionsResponse = [];

            foreach ($responseData['data']['webhookSubscriptions']['edges'] as $response) {
                $webhookSubscriptionsResponse[]['id'] = $response['node']['id'] ?? '';
                $webhookSubscriptionsResponse[]['callbackUrl'] = $response['node']['endpoint']['callbackUrl'] ?? '';
                $webhookSubscriptionsResponse[]['topic'] = $response['node']['topic'] ?? '';
                $webhookSubscriptionsResponse[]['createdAt'] = $response['node']['createdAt'] ?? '';
                $webhookSubscriptionsResponse[]['updatedAt'] = $response['node']['updatedAt'] ?? '';
                $webhookSubscriptionsResponse[]['format'] = $response['node']['format'];
            }

            $responseData = $webhookSubscriptionsResponse;

        }

        return $responseData;
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

        if (isset($responseData['data']['webhookSubscriptionCreate']['webhookSubscription']['userErrors']) && !empty($responseData['data']['webhookSubscriptionCreate']['webhookSubscription']['userErrors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['webhookSubscriptionCreate']['webhookSubscription']['userErrors'], true));

        } else {

            $webhookSubscriptionsCreateResponse = [];

            foreach ($responseData['data']['webhookSubscriptionCreate']['webhookSubscription'] as $response) {
                $webhookSubscriptionsCreateResponse['id'] = str_replace("gid://shopify/WebhookSubscription/", "", $response['id']) ?? '';
                $webhookSubscriptionsCreateResponse['topic'] = $response['topic'] ?? '';
                $webhookSubscriptionsCreateResponse['created_at'] = $response['createdAt'] ?? '';
                $webhookSubscriptionsCreateResponse['updated_at'] = $response['updatedAt'] ?? '';
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



        $webhookUpdateVariable = [
            'id' => 'gid://shopify/WebhookSubscription/' . $param['id'],
            'webhookSubscription' => [
                'callbackUrl' => $param['webhookSubscription']['callbackUrl'],
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

        if (isset($responseData['data']['webhookSubscriptionUpdate']['webhookSubscription']['userErrors']) && !empty($responseData['data']['webhookSubscriptionUpdate']['webhookSubscription']['userErrors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['webhookSubscriptionUpdate']['webhookSubscription']['userErrors'], true));

        } else {

            $webhookSubscriptionsUpdateResponse = [];

            foreach ($responseData['data']['webhookSubscriptionUpdate']['webhookSubscription'] as $response) {
                $webhookSubscriptionsUpdateResponse[]['id'] = $response['id'] ?? '';
                $webhookSubscriptionsUpdateResponse[]['topic'] = $response['topic'] ?? '';
                $webhookSubscriptionsUpdateResponse[]['createdAt'] = $response['createdAt'] ?? '';
                $webhookSubscriptionsUpdateResponse[]['updatedAt'] = $response['updatedAt'] ?? '';
                $webhookSubscriptionsUpdateResponse[]['format'] = $response['format'];
                $webhookSubscriptionsUpdateResponse[]['callbackUrl'] = $response['endpoint']['callbackUrl'] ?? '';
            }

            $responseData = $webhookSubscriptionsUpdateResponse;

        }

        return $responseData;
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

            throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['webhookSubscriptionDelete']['userErrors'], true));

        } else {

            $responseData = ['data']['webhookSubscriptionDelete'];

        }

        return $responseData;
    }

}
