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
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/themes
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/theme#get-themes
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

            $themeResponse = [];

            foreach ($responseData['data']['themes']['edges'] as $response) {
                $themeResponse[] = [
                    'id' => str_replace('gid://shopify/OnlineStoreTheme/', '', $response['node']['id']),
                    'name' => $response['node']['name'] ?? '',
                    'created_at' => $response['node']['createdAt'] ?? '',
                    'updated_at' => $response['node']['updatedAt'] ?? '',
                    'role' => strtolower($response['node']['role']) ?? '',
                    'theme_store_id' => $response['node']['themeStoreId'] ?? '',
                    'processing' => $response['node']['processing'] ?? '',
                ];
            }

            $responseData = $themeResponse;

        }

        return $responseData;
    }

    /** 
     * To get all users theme use this function.
     */
    public function themesByRole($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/themes
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/theme#get-themes
        */

        $themeVariable = [
            'roles' => strtoupper($param['role']),
        ];

        $themeQuery = <<<'GRAPHQL'
        query MainTheme($roles: [ThemeRole!]) {
            themes(first: 250, roles: $roles) {
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

        $responseData = $this->graphqlService->graphqlQueryThalia($themeQuery, $themeVariable);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {

            throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

        } else {

            $themeResponse = [];

            foreach ($responseData['data']['themes']['edges'] as $response) {
                $themeResponse[] = [
                    'id' => str_replace('gid://shopify/OnlineStoreTheme/', '', $response['node']['id']),
                    'name' => $response['node']['name'] ?? '',
                    'created_at' => $response['node']['createdAt'] ?? '',
                    'updated_at' => $response['node']['updatedAt'] ?? '',
                    'role' => $response['node']['role'] ?? '',
                    'theme_store_id' => $response['node']['themeStoreId'] ?? '',
                    'processing' => $response['node']['processing'] ?? '',
                ];
            }

            $responseData = $themeResponse;

        }

        return $responseData;
    }

    /** 
     * To get users theme by its Id use this function.
     */
    public function getThemeById($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/theme?example=Retrieves+a+single+theme+by+its+ID
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/theme#get-themes-theme-id
        */

        $themeVariable = [
            'id' => 'gid://shopify/OnlineStoreTheme/' . $param['theme_id'],
        ];

        $themeQuery = <<<'GRAPHQL'
        query Theme ($id: ID!) {
            theme(id: $id) {
                createdAt
                id
                name
                prefix
                processing
                processingFailed
                role
                themeStoreId
                updatedAt
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($themeQuery, $themeVariable);

        if (isset($responseData['data']['theme']['errors']) && !empty($responseData['errors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

        } else {

            $themeResponse = [
                'id' => str_replace('gid://shopify/OnlineStoreTheme/', '', $responseData['data']['theme']['id']),
                'name' => $responseData['data']['theme']['name'] ?? '',
                'created_at' => $responseData['data']['theme']['createdAt'] ?? '',
                'updated_at' => $responseData['data']['theme']['updatedAt'] ?? '',
                'role' => $responseData['data']['theme']['role'] ?? '',
                'theme_store_id' => $responseData['data']['theme']['themeStoreId'] ?? '',
                'processing' => $responseData['data']['theme']['processing'] ?? '',
                'admin_graphql_api_id' => $responseData['data']['theme']['id'] ?? '',
            ];

            $responseData = $themeResponse;

        }

        return $responseData;
    }

    /** 
     * To get users theme files use this function.
     */
    public function themesFiles($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/theme?example=Retrieves+a+list+of+assets+for+a+theme&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/asset#get-themes-theme-id-assets
        */

        $themeFilesVariable = [
            'themeId' => 'gid://shopify/OnlineStoreTheme/' . $param['theme_id'],
        ];

        $themeFilesQuery = <<<'GRAPHQL'
        query ThemeFilesPaginated($themeId: ID!, $after: String) {
            theme(id: $themeId) {
                files(first: 250, after: $after) {
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

        $themeFilesResponse = [];
        $pageInfo = null;
        $afterCursor = null;

        do {
            $themeFilesVariable['after'] = $afterCursor;

            $responseData = $this->graphqlService->graphqlQueryThalia($themeFilesQuery, $themeFilesVariable);


            if (isset($responseData['errors']) && !empty($responseData['errors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['errors']);
            }

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
            $afterCursor = $pageInfo['endCursor'];

        } while ($pageInfo['hasNextPage']);


        return [
            'data' => $themeFilesResponse,
            'pageInfo' => $pageInfo,
        ];

    }

    /** 
     * To set/update users theme files use this function.
     */
    public function themeFilesUpsert($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/themeFilesUpsert
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/asset#put-themes-theme-id-assets
        */

        $themeFileUpsertVariable = [
            'themeId' => 'gid://shopify/OnlineStoreTheme/' . $param['theme_id'],
            'files' => [
                'filename' => $param['asset']['filename'],
                'body' => [
                    'type' => $param['asset']['type'],
                    'value' => $param['asset']['value'],
                ]
            ]
        ];

        $themeFileUpsertQuery = <<<'GRAPHQL'
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

        $responseData = $this->graphqlService->graphqlQueryThalia($themeFileUpsertQuery, $themeFileUpsertVariable);

        if (isset($responseData['data']['themeFilesUpsert']['userErrors']) && !empty($responseData['data']['themeFilesUpsert']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['themeFilesUpsert']['userErrors']);

        } else {

            $themeFileUpsertResponse = [];

            $themeFileUpsertResponse['key'] = $responseData['data']['themeFilesUpsert']['upsertedThemeFiles'][0]['filename'];
            $responseData = $themeFileUpsertResponse;

        }

        return $responseData;
    }

    /** 
     * To delete users theme files use this function.
     */
    public function themeFilesDelete($param)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/themeFilesDelete?example=Deletes+an+asset+from+a+theme&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/asset#delete-themes-theme-id-assets?asset[key]=assets-bg-body.gif
        */

        $themeFileDeleteVariable = [
            'themeId' => 'gid://shopify/OnlineStoreTheme/' . $param['theme_id'],
            'files' => $param['asset']['key'],
        ];

        $themeFileDeleteQuery = <<<'GRAPHQL'
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

        $responseData = $this->graphqlService->graphqlQueryThalia($themeFileDeleteQuery, $themeFileDeleteVariable);

        if (isset($responseData['data']['themeFilesDelete']['userErrors']) && !empty($responseData['data']['themeFilesDelete']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['themeFilesDelete']['userErrors']);

        } else {

            $themeFileDeleteResponse = [];

            $themeFileDeleteResponse['key'] = $responseData['data']['themeFilesDelete']['deletedThemeFiles'][0]['filename'];
            $responseData = $themeFileDeleteResponse;

            $responseData = $themeFileDeleteResponse;

        }

        return $responseData;
    }

    /** 
     * To get asset file contents
     */
    public function onlineStoreThemeFileBodyText($themeId, $filename)
    {

         /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2024-10/queries/theme
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2025-07/resources/asset#get-themes-theme-id-assets?asset[key]=templates-index.liquid
        */

        $query = <<<QUERY
        query getThemeFiles(\$themeId: ID!, \$filenames: [String!]!) {
            theme(id: \$themeId) {
            id
            name
            role
            createdAt
            updatedAt
            files(filenames: \$filenames, first: 1) {
                nodes {
                body {
                    ... on OnlineStoreThemeFileBodyText {
                    content
                    }
                }
                }
            }
            }
        }
        QUERY;

        $variables = [
            "themeId" => "gid://shopify/OnlineStoreTheme/{$themeId}", // Replace dynamically
            "filenames" => [$filename] // Replace dynamically
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        
    
        if (isset($responseData['data']['getThemeFiles']['userErrors']) && !empty($responseData['data']['getThemeFiles']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['getThemeFiles']['userErrors']);

        }else{
            $fileContent = $responseData['data']['theme']['files']['nodes'][0]['body']['content'] ?? "";

            $formattedResponse = [
                    "key" => $filename,
                    "value" => $fileContent,
                    "created_at" => $responseData['data']['theme']['createdAt'] ?? '',
                    "updated_at" => $responseData['data']['theme']['updatedAt'] ?? '',
                    "theme_id" => (int) $themeId
            ];

            return $formattedResponse;
        }
        
        
       
    }

}
