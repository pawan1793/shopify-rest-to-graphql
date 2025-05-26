<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use Thalia\ShopifyRestToGraphql\GraphqlException;
use Thalia\ShopifyRestToGraphql\GraphqlService;

class OptionEndpoints
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

    public function generateOptions($attributeName, $values)
    {
        $formattedValues = array_map(function ($value) {
            return ["name" => $value];
        }, $values);

        return [
            "name" => $attributeName,
            "values" => $formattedValues
        ];
    }

    public function findVariantIdByValue($variants, $optionName, $targetValue = null) {
        foreach ($variants as $variant) {
            if ($targetValue === null) {
                if ($variant['title'] === $optionName) {
                    return str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                }
            }

            foreach ($variant['selectedOptions'] as $option) {
                if ($option['name'] === $optionName && $option['value'] === $targetValue) {
                    return str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                }
            }
        }

        return null;
    }

    public function findOptionIdByValue($options, $optionName) {
        foreach ($options as $option) {
            if ($option['name'] === $optionName) {
                return str_replace("gid://shopify/ProductOption/", "", $option['id']);
            }
        }

        return null;
    }

    public function findOptionValueIdByValue($options, $optionName) {
        foreach ($options as $option) {
            foreach ($option['optionValues'] as $optionValue) {
                if ($optionValue['name'] === $optionName) {
                    return str_replace("gid://shopify/ProductOptionValue/", "", $optionValue['id']);
                }
            }
        }

        return null;
    }

    /**
     * Create Product Option
     */
    public function productOpitonsCreate($productId, $options, $variantStrategy = 'CREATE')
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/productoptionscreate
        */

        $query = <<<'GRAPHQL'
              mutation createOptions($productId: ID!, $options: [OptionCreateInput!]!, $variantStrategy: ProductOptionCreateVariantStrategy) {
              productOptionsCreate(productId: $productId, options: $options, variantStrategy: $variantStrategy) {
                userErrors {
                  field
                  message
                  code
                }
                product {
                  id
                  variants(first: 10) {
                    nodes {
                      id
                      title
                      selectedOptions {
                        name
                        value
                      }
                    }
                  }
                  options {
                    id
                    name
                    values
                    position
                    optionValues {
                      id
                      name
                      hasVariants
                    }
                  }
                }
              }
            }
            GRAPHQL;

        $productId = str_replace("gid://shopify/Product/", "", $productId);
        $variables = [
            'productId' => "gid://shopify/Product/".$productId,
            'options' => $options,
            'variantStrategy' => $variantStrategy,
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['data']['productOptionsCreate']['userErrors']) && !empty($responseData['data']['productOptionsCreate']['userErrors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productOptionsCreate']['userErrors']);
        } else {
            $responseData = $responseData['data']['productOptionsCreate'];
        }


        return $responseData;
    }

    /**
     * Update Product Option
     */
    public function productOpitonsUpdate($productId, $options, $variantStrategy = 'CREATE')
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2025-01/mutations/productoptionscreate
        */

        $query = <<<'GRAPHQL'
              mutation createOptions($productId: ID!, $options: [OptionCreateInput!]!, $variantStrategy: ProductOptionCreateVariantStrategy) {
              productOptionsCreate(productId: $productId, options: $options, variantStrategy: $variantStrategy) {
                userErrors {
                  field
                  message
                  code
                }
                product {
                  id
                  variants(first: 10) {
                    nodes {
                      id
                      title
                      selectedOptions {
                        name
                        value
                      }
                    }
                  }
                  options {
                    id
                    name
                    values
                    position
                    optionValues {
                      id
                      name
                      hasVariants
                    }
                  }
                }
              }
            }
            GRAPHQL;

        $productId = str_replace("gid://shopify/Product/", "", $productId);
        $variables = [
            'productId' => "gid://shopify/Product/".$productId,
            'options' => $options,
            'variantStrategy' => $variantStrategy,
        ];

        $responseData = $this->graphqlService->graphqlQueryThalia($query, $variables);

        if (isset($responseData['data']['productOptionsCreate']['userErrors']) && !empty($responseData['data']['productOptionsCreate']['userErrors'])) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productOptionsCreate']['userErrors']);
        } else {
            $responseData = $responseData['data']['productOptionsCreate'];
        }


        return $responseData;
    }
}
