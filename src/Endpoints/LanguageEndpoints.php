<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlService;
use Thalia\ShopifyRestToGraphql\GraphqlException;

class LanguageEndpoints
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
     * To get all published languages for the shop.
     */
    public function publishedLanguages()
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/latest/queries/shopLocales
        */

        $languagesQuery = <<<'GRAPHQL'
        query GetPublishedLanguages {
            shopLocales {
                locale
                name
                primary
                published
            }
        }
        GRAPHQL;

        $responseData = $this->graphqlService->graphqlQueryThalia($languagesQuery);
        // dd($responseData);

        if (isset($responseData['errors']) && !empty($responseData['errors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
        } else {
            $languages = [];
            foreach ($responseData['data']['shopLocales'] as $edge) {
                
                if ($edge['published']) {
                    $languages[] = [
                        'locale' => $edge['locale'],
                        'name' => $edge['name'],
                        'primary' => $edge['primary'],
                        'published' => $edge['published'],
                    ];
                }
            }
            // dd($languages);
            return $languages;
        }
    }
}