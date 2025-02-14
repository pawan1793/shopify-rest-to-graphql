<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class ThemesEndpoints
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
     * To get all users theme use this function.
     */
    public function themes()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/themes
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/theme#get-themes
        */



        $themeQuery = <<<"GRAPHQL"
        query ThemeList {
            themes(first: 250) {
                edges {
                    node {
                        id
                        name
                        prefix
                        processing
                        processingFailed
                        role
                        themeStoreId
                        createdAt
                        updatedAt
                    }
                    cursor
                }
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($themeQuery);

        if (isset($responseData['data']['themes']['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $webhookSubscriptionsResponse = [];

            foreach ($responseData['data']['themes']['edges'] as $response) {
                $webhookSubscriptionsResponse[]['id'] = str_replace('gid://shopify/OnlineStoreTheme/', '', $response['node']['id']);
                $webhookSubscriptionsResponse[]['name'] = $response['node']['name'] ?? '';
                $webhookSubscriptionsResponse[]['role'] = $response['node']['role'] ?? '';
                $webhookSubscriptionsResponse[]['theme_store_id'] = $response['node']['themeStoreId'] ?? '';
                $webhookSubscriptionsResponse[]['processing'] = $response['node']['processing'] ?? '';
                $webhookSubscriptionsResponse[]['admin_graphql_api_id'] = $response['node']['admin_graphql_api_id'] ?? '';
                $webhookSubscriptionsResponse[]['createdAt'] = $response['node']['createdAt'] ?? '';
                $webhookSubscriptionsResponse[]['updatedAt'] = $response['node']['updatedAt'] ?? '';
            }

            $responseData = $webhookSubscriptionsResponse;

        }

        return $responseData;
    }

    /** 
     * To get users theme files use this function.
     */
    public function themesFiles($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/queries/theme?example=Retrieves+a+list+of+assets+for+a+theme&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/asset#get-themes-theme-id-assets
        */



        $themefileUpsertVariable = [
            'themeId' => 'gid://shopify/OnlineStoreTheme/' . $param['themeId'],
        ];

        $themeQuery = <<<'GRAPHQL'
        query ThemeFilesPaginated($themeId: ID!) {
            theme(id: $themeId) {
                files(first: 50) {
                    edges {
                        node {
                            filename
                            createdAt
                            updatedAt
                            contentType
                            size
                            checksumMd5
                            body {
                                ... on OnlineStoreThemeFileBodyBase64 {
                                    contentBase64
                                }
                                ... on OnlineStoreThemeFileBodyText {
                                    content
                                }
                                ... on OnlineStoreThemeFileBodyUrl {
                                    url
                                }
                            }
                        }
                        cursor
                    }
                    pageInfo {
                        endCursor
                        hasNextPage
                        hasPreviousPage
                        startCursor
                    }
                    userErrors {
                        code
                        filename
                    }
                }
            }
        }
        GRAPHQL;

        $responseData = $graphqlService->graphqlQueryThalia($themeQuery, $themefileUpsertVariable);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);

        } else {

            $themeFilesResponse = [];

            foreach ($responseData['data']['theme']['files']['edges'] as $response) {
                $themeFilesResponse[] = [
                'key' => $response['node']['filename'] ?? '',
                'created_at' => $response['node']['createdAt'] ?? '',
                'updated_at' => $response['node']['updatedAt'] ?? '',
                'content_type' => $response['node']['contentType'] ?? '',
                'size' => $response['node']['size'] ?? '',
                'checksum' => $response['node']['checksumMd5'] ?? '',
                'body' => $response['node']['body'] ?? '',
                'cursor' => $response['cursor'] ?? '',
                ];
            }

            $pageInfo = $responseData['data']['theme']['files']['pageInfo'];

            $responseData = [
                'data' => $themeFilesResponse,
                'pageInfo' => $pageInfo,
            ];

        }

        return $responseData;
    }

    /** 
     * To set/update users theme files use this function.
     */
    public function themeFilesUpsert($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/themeFilesUpsert
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/asset#put-themes-theme-id-assets
        */



        $themefileUpsertVariable = [
            'themeId' => 'gid://shopify/OnlineStoreTheme/' . $param['asset']['themeId'],
            'files' => [
                'filename' => $param['asset']['filename'],
                'body' => [
                    'type' => $param['asset']['type'],
                    'value' => $param['asset']['value'],
                ]
            ]
        ];

        $themefileUpsertQuery = <<<'GRAPHQL'
            mutation themeFilesUpsert($files: [OnlineStoreThemeFilesUpsertFileInput!]!, $themeId: ID!) {
                themeFilesUpsert(files: $files, themeId: $themeId) {
                    upsertedThemeFiles {
                        filename
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($themefileUpsertQuery, $themefileUpsertVariable);

        if (isset($responseData['data']['themeFilesUpsert']['userErrors']) && !empty($responseData['data']['themeFilesUpsert']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['themeFilesUpsert']['userErrors']);

        } else {

            $themefileUpsertResponse = [];

            foreach ($responseData['data']['themeFilesUpsert']['upsertedThemeFiles'] as $response) {
                $themefileUpsertResponse[]['filename'] = $response['filename'] ?? '';
            }

            $responseData = $themefileUpsertResponse;

        }

        return $responseData;
    }

    /** 
     * To delete users theme files use this function.
     */
    public function themeFilesDelete($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/themeFilesDelete?example=Deletes+an+asset+from+a+theme&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-01/resources/asset#delete-themes-theme-id-assets?asset[key]=assets-bg-body.gif
        */



        $themefileDeleteVariable = [
            'themeId' => 'gid://shopify/OnlineStoreTheme/' . $param['themeId'],
            'files' => $param['files'],
        ];

        $themefileDeleteQuery = <<<'GRAPHQL'
            mutation ThemeFilesDelete($files: [String!]!, $themeId: ID!) {
                themeFilesDelete(files: $files, themeId: $themeId) {
                    deletedThemeFiles {
                        filename
                    }
                    userErrors {
                        code
                        field
                        filename
                        message
                    }
                }
            }
            GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($themefileDeleteQuery, $themefileDeleteVariable);

        if (isset($responseData['data']['themeFilesDelete']['userErrors']) && !empty($responseData['data']['themeFilesDelete']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['themeFilesDelete']['userErrors']);

        } else {

            $themefileUpsertResponse = [];

            foreach ($responseData['data']['themeFilesDelete']['deletedThemeFiles'] as $response) {
                $themefileUpsertResponse[]['filename'] = $response['filename'] ?? '';
            }

            $responseData = $themefileUpsertResponse;

        }

        return $responseData;
    }


}
