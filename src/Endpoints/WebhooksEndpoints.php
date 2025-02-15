<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
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

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

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

        if($webhookParams['topic'] == 'app/uninstalled'){
            $webhookParams['topic'] = 'APP_UNINSTALLED';
        }

        if($webhookParams['format'] == 'json'){
            $webhookParams['topic'] = 'JSON';
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
                if($response['topic'] == 'APP_UNINSTALLED'){
                    $response['topic'] = 'app/uninstalled';
                }
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

        if (isset($responseData['data']['webhookSubscriptionUpdate']['userErrors']) && !empty($responseData['data']['webhookSubscriptionUpdate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['webhookSubscriptionUpdate']['userErrors']);

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

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['webhookSubscriptionDelete']['userErrors']);

        } else {

            $responseData = ['data']['webhookSubscriptionDelete'];

        }

        return $responseData;
    }

}
