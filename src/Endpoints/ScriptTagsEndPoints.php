<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;
class ScriptTagsEndPoints
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

    public function getScriptTags()
    {
        /*
          Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/queries/scriptTags?example=Retrieves+a+list+of+all+script+tags
          Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-10/resources/scripttag#get-script-tags
      */
        $query = <<<'GRAPHQL'
                query GetScriptTags($first: Int!, $cursor: String) {
                    scriptTags(first: $first, after: $cursor) {
                    nodes {
                        id
                        cache
                        createdAt
                        displayScope
                        src
                        updatedAt
                    }
                    pageInfo {
                        startCursor
                        endCursor
                    }
                    }
                }
                GRAPHQL;

        $variables = [
            "first" => 5,
            "cursor" => null,
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);


        if (isset($responseData['data']['scriptTags']['userErrors']) && !empty($responseData['data']['scriptTags']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['scriptTags']['userErrors']);

        } else {

            $script_tags = array_map(function ($tag) {
                return [
                    "id" => (int) preg_replace('/\D/', '', $tag["id"]), // Extract numeric ID
                    "src" => strtok($tag["src"], '?'), // Remove query params
                    "event" => "onload", // Default event type
                    "created_at" => $tag["createdAt"],
                    "updated_at" => $tag["updatedAt"],
                    "display_scope" => strtolower($tag["displayScope"]),
                    "cache" => (bool) $tag["cache"]
                ];
            }, $responseData["data"]["scriptTags"]["nodes"]);

            $result = ["script_tags" => $script_tags];

            return $result;

        }


    }


    public function scriptTagCreate($params)
    {
        /*
          Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/scriptTagCreate?example=Creates+a+new+script+tag&language=PHP
          Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-10/resources/scripttag#post-script-tags
      */
        $query = <<<'GRAPHQL'
                mutation ScriptTagCreate($input: ScriptTagInput!) {
                    scriptTagCreate(input: $input) {
                    scriptTag {
                        id
                        cache
                        createdAt
                        displayScope
                        src
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                    }
                    }
                }
                GRAPHQL;

        $variables = [
            "input" => [
                "src" => $params['script_tag']['src'],
                "displayScope" => "ONLINE_STORE",
                "cache" => true,
            ],
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['data']['scriptTagCreate']['userErrors']) && !empty($responseData['data']['scriptTagCreate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['scriptTagCreate']['userErrors']);

        } else {
            $response = [
                "script_tag" => [
                    "id" => (int) preg_replace('/\D/', '', $responseData["data"]["scriptTagCreate"]["scriptTag"]["id"]),
                    "src" => $responseData["data"]["scriptTagCreate"]["scriptTag"]["src"],
                    "event" => "onload", // Assuming default value as "event" is not in input
                    "created_at" => $responseData["data"]["scriptTagCreate"]["scriptTag"]["createdAt"],
                    "updated_at" => $responseData["data"]["scriptTagCreate"]["scriptTag"]["updatedAt"],
                    "display_scope" => strtolower($responseData["data"]["scriptTagCreate"]["scriptTag"]["displayScope"]),
                    "cache" => (bool) $responseData["data"]["scriptTagCreate"]["scriptTag"]["cache"]
                ]
            ];

            return $response;

        }


    }


    public function scriptTagUpdate($params)
    {
        /*
          Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/mutations/scriptTagUpdate
          Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-10/resources/scripttag#update-script-tags
      */
        $query = <<<'GRAPHQL'
                mutation ScriptTagUpdate($id: ID!, $input: ScriptTagInput!) {
                    scriptTagUpdate(id: $id, input: $input) {
                        scriptTag {
                            id
                            cache
                            createdAt
                            displayScope
                            src
                            updatedAt
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
                GRAPHQL;

        $scriptid = $params['script_tag']['id'];

        $variables = [
           "id" => "gid://shopify/ScriptTag/{$scriptid}",
            "input" => [
                "src" => $params['script_tag']['src'],
                "displayScope" => "ONLINE_STORE",
                "cache" => true,
            ],
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['data']['scriptTagUpdate']['userErrors']) && !empty($responseData['data']['scriptTagUpdate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['scriptTagUpdate']['userErrors']);

        } else {
            $response = [
                "script_tag" => [
                    "id" => (int) preg_replace('/\D/', '', $responseData["data"]["scriptTagUpdate"]["scriptTag"]["id"]),
                    "src" => $responseData["data"]["scriptTagUpdate"]["scriptTag"]["src"],
                    "event" => "onload", // Assuming default value as "event" is not in input
                    "created_at" => $responseData["data"]["scriptTagUpdate"]["scriptTag"]["createdAt"],
                    "updated_at" => $responseData["data"]["scriptTagUpdate"]["scriptTag"]["updatedAt"],
                    "display_scope" => strtolower($responseData["data"]["scriptTagUpdate"]["scriptTag"]["displayScope"]),
                    "cache" => (bool) $responseData["data"]["scriptTagUpdate"]["scriptTag"]["cache"]
                ]
            ];

            return $response;

        }


    }

    public function deleteScriptTags($scriptid)
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-07/mutations/scriptTagDelete?example=Deletes+a+script+tag&language=PHP
            Rest Reference : https://shopify.dev/docs/api/admin-rest/2024-10/resources/scripttag#delete-script-tags-script-tag-id
        */

        $query = <<<'GRAPHQL'
                    mutation ScriptTagDelete($id: ID!) {
                    scriptTagDelete(id: $id) {
                        deletedScriptTagId
                        userErrors {
                        field
                        message
                        }
                    }
                    }
                    GRAPHQL;

        $variables = [
            "id" => "gid://shopify/ScriptTag/{$scriptid}",
        ];


        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['data']['scriptTagDelete']['userErrors']) && !empty($responseData['data']['scriptTagDelete']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['scriptTagDelete']['userErrors']);

        } else {

            $responseData = true;

        }

        return $responseData;
    }


}
