<?php

namespace Thalia\ShopifyRestToGraphql;
use Thalia\ShopifyRestToGraphql\GraphqlException;
use GuzzleHttp\Client;

class GraphqlService
{


    private $shopDomain;
    private $accessToken;
    private $client;

    public function __construct(string $shopDomain = null, string $accessToken = null)
    {

        if ($shopDomain === null || $accessToken === null) {
            throw new \InvalidArgumentException('Shop domain and access token must be provided.');
        }


        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
        $this->client = new Client([
            'base_uri' => "https://{$this->shopDomain}/admin/api/2025-01/graphql.json",
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);


    }





    public function graphqlQueryThalia(string $query, array $variables = []): array
    {

        try {

            if (!empty($variables)) {
                $response = $this->client->post('', [
                    'json' => [
                        'query' => $query,
                        'variables' => $variables,
                    ],
                ]);
            } else {
                $response = $this->client->post('', [
                    'json' => [
                        'query' => $query
                    ],
                ]);
            }

            $responseData = json_decode($response->getBody(), true);

            // Check for throttling error
            if (isset($responseData['errors']) && isset($responseData['errors'][0]['extensions']['code']) && $responseData['errors'][0]['extensions']['code'] === 'THROTTLED') {
                throw new GraphqlException('Shopify API request throttled', 503, $responseData['errors'], null);
            }

            return $responseData;

        } catch (GraphqlException $e) {
            if ($e->getCode() === 503) {
                throw $e; // Just rethrow throttling errors as-is
            }
            // Handle other GraphQL errors
            throw new GraphqlException($e->getMessage(), $e->getCode(), $e->getErrors(), $e);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Covers ClientException, ServerException
            $responseBody = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : 'No response from server.';

            $responseArray = json_decode($responseBody, true);
            $errors = $responseArray['errors'] ?? [['message' => $responseBody]];

            throw new GraphqlException("Shopify API request failed", $e->getCode(), (array) $errors, $e);

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Handle network issues (DNS, timeout, etc.)
            $errors = [['message' => 'Connection failed: ' . $e->getMessage()]];

            throw new GraphqlException("Shopify API connection error", $e->getCode(), $errors, $e);

        } catch (\Exception $e) {
            // Generic fallback
            $errors = [['message' => 'Unexpected error: ' . $e->getMessage()]];

            throw new GraphqlException("Unexpected Shopify API error", $e->getCode(), $errors, $e);
        }
    }


    /**
     * Send a GraphQL query to Shopify to fetch the list of publications,create product and update variant 
     * identify the "Online Store" publication, and process the response.
     *
     * @param array  $params       An array of parameters for the GraphQL query.
     * @param string $shop         The Shopify shop domain.
     * @param string $accessToken  The access token for authenticating the Shopify API request.
     *
     * @return array The processed data containing "id" and "variants".
     *
     * @throws \Exception If there is an error with the HTTP request or GraphQL response.
     */





    public function graphqlPostProduct($params)
    {


        $onlinepublication = [];
        $query = <<<QUERY
                query publications {
                    publications(first: 5) {
                    edges {
                        node {
                        id
                        name
                        
                        }
                    }
                    }
                }
                QUERY;










        try {
            // Send GraphQL request

            $responseData = $this->graphqlQueryThalia($query);


            if (isset($responseData["errors"])) {
                throw new GraphqlException("Shopify API request failed", 400, $responseData["errors"]);
                
            } else {
                foreach ($responseData["data"]["publications"]["edges"] as $key => $publication) {
                    if ($publication["node"]["name"] == "Online Store") {
                        $onlinepublication = $publication["node"];
                    }

                }

            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new GraphqlException("Shopify API request failed", 400,[],$e);

        }




        $productdata = $params['product'];

        $product['title'] = $productdata['title'];
        $product['descriptionHtml'] = $productdata['body_html'];
        $product['productType'] = $productdata['product_type'];
        $product['vendor'] = $productdata['vendor'];
        $product['tags'] = $productdata['tags'];
        $product['templateSuffix'] = $productdata['template_suffix'];

        if (isset($productdata['seo'])) {


            $product['seo']['title'] = isset($productdata['seo']['title']) ? $productdata['seo']['title'] : '';
            $product['seo']['description'] = isset($productdata['seo']['description']) ? $productdata['seo']['description'] : '';

        }

        if (isset($productdata['published'])) {
            if ($productdata['published'] == false) {
                $product['status'] = 'DRAFT';
            }
        }

        $product['publications'][]['publicationId'] = $onlinepublication['id'];

        if (!empty($productdata['metafields'])) {

            foreach ($productdata['metafields'] as $metafieldkey => $metafield) {
                if (is_integer($metafield['value'])) {
                    $productdata['metafields'][$metafieldkey]['value'] = (string) $metafield['value'];
                }
                $product['metafields'] = $productdata['metafields'];

            }
        }




        if (!empty($productdata['collection'])) {
            foreach ($productdata['collection'] as $collectionid => $collection) {
                $product['collectionsToJoin'][] = "gid://shopify/Collection/{$collectionid}";
            }
        }
        $productmedia = array();
        if (!empty($productdata['images'])) {
            foreach ($productdata['images'] as $imagekey => $image) {

                if(empty($image['src'])){
                    continue;
                }

                if (count($productmedia) > 249) {
                    break;
                }

                $gqimage = [];
                $gqimage['originalSource'] = $image['src'];
                if (isset($image['alt'])) {
                    $gqimage['alt'] = $image['alt'];
                }

                $gqimage['mediaContentType'] = "IMAGE";
                $productmedia[] = $gqimage;
            }
        }

        $productquery = <<<'GRAPHQL'
                    mutation CreateProduct($input: ProductInput!, $mediainput: [CreateMediaInput!]) {
                    productCreate(input: $input, media: $mediainput) {
                        product {
                        id
                        title
                        variants(first: 1) {
                            edges {
                                node {
                                    id
                                    title
                                    barcode
                                }
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



        $variables = [
            'input' => $product,
            'mediainput' => $productmedia,
        ];








        $productreturndata = array();
        if (1) {

                $responseData = $this->graphqlQueryThalia($productquery, $variables);


                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productCreate']['userErrors']);

                } else {
                    // Print the created product details
                    $variantid = $responseData['data']['productCreate']['product']['variants']['edges'][0]['node']['id'];


                    $productId = $responseData['data']['productCreate']['product']['id'];

                    $productreturndata['id'] = str_replace("gid://shopify/Product/", "", $productId);
                    $productreturndata['variants'][0]['id'] = str_replace("gid://shopify/ProductVariant/", "", $variantid);

                }

        }

        if (!isset($variantid)) {
            return array();
        }

        $variant = $productdata['variants'][0];


        $variantdata['id'] = $variantid;

        if (!empty($variant['compare_at_price'])) {
            $variantdata['compareAtPrice'] = $variant['compare_at_price'];
        }

        if (!empty($variant['price'])) {
            $variantdata['price'] = $variant['price'];
        }

        if (!empty($variant['barcode'])) {
            $variantdata['barcode'] = isset($variant['barcode']) ? $variant['barcode'] : null;
        }

        if (isset($variant['taxable'])) {
            $variantdata['taxable'] = isset($variant['taxable']) ? $variant['taxable'] : true;
        }

        if (!empty($variant['sku'])) {
            $variantdata['inventoryItem']['sku'] = $variant['sku'];
        }
        
        if (!empty($variant['cost'])) {
            $variantdata['inventoryItem']['cost'] = $variant['cost'];
            $variantdata['inventoryItem']['tracked'] = true;
            if(isset($variant['inventory_management']) && $variant['inventory_management'] == ''){
                 $variantdata['inventoryItem']['tracked'] = false;
            }
        }

        if(!empty($variant['inventory_management_tracked'])){
             $variantdata['inventoryItem']['tracked'] = true;
        }



        if (isset($variant['weight'])) {

            $variantdata['inventoryItem']['measurement']['weight']['value'] = (float) $variant['weight'];
            if (isset($variant['weight_unit'])) {
                switch ($variant['weight_unit']) {
                    case 'lb':
                        $weight_unit = 'POUNDS';
                        break;

                    case 'kg':
                        $weight_unit = 'KILOGRAMS';
                        break;

                    default:
                        $weight_unit = 'KILOGRAMS';
                        break;
                }

                $variantdata['inventoryItem']['measurement']['weight']['unit'] = $weight_unit;
            }

        }



        $finalvariantvariables['productId'] = $productId;
        $finalvariantvariables['variants'][] = $variantdata;


        if (1) {


            $variantquery = <<<'GRAPHQL'
                        mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                        productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                            product {
                                id
                            }
                            productVariants {
                                id
                                title
                                inventoryItem{
                                    id
                                }
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                        }
                        GRAPHQL;



        }






        try {
            // Send GraphQL request
            $responseData = $this->graphqlQueryThalia($variantquery, $finalvariantvariables);
            // Check for GraphQL or user errors
            if (isset($responseData['errors'])) {

                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

            } elseif (isset($responseData['data']['productVariantsBulkUpdate']['userErrors']) && !empty($responseData['data']['productVariantsBulkUpdate']['userErrors'])) {

                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariantsBulkUpdate']['userErrors']);

            } else {
                // Print the created product details

                $inventory_item_id = $responseData['data']['productVariantsBulkUpdate']['productVariants'][0]['inventoryItem']['id'];

                $inventory_item_id = str_replace("gid://shopify/InventoryItem/", "", $inventory_item_id);
                $productreturndata['variants'][0]['inventory_item_id'] = $inventory_item_id;
                //inventory_item_id

            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
        }

        return $productreturndata;
    }



    public function graphqlPostProductWithVariants($params)
    {


        $onlinepublication = [];
        $query = <<<QUERY
                query publications {
                    publications(first: 5) {
                    edges {
                        node {
                        id
                        name
                        
                        }
                    }
                    }
                }
                QUERY;



        try {

            $responseData = $this->graphqlQueryThalia($query);


            if (isset($responseData["errors"])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
            } else {
                foreach ($responseData["data"]["publications"]["edges"] as $key => $publication) {
                    if ($publication["node"]["name"] == "Online Store") {
                        $onlinepublication = $publication["node"];
                    }

                }

            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);

        }




        $productdata = $params['product'];

        $product['title'] = $productdata['title'];
        $product['descriptionHtml'] = $productdata['body_html'];
        $product['productType'] = $productdata['product_type'];
        $product['vendor'] = $productdata['vendor'];
        $product['tags'] = $productdata['tags'];
        $product['templateSuffix'] = $productdata['template_suffix'];

        if (isset($productdata['published'])) {
            if ($productdata['published'] == false) {
                $product['status'] = 'DRAFT';
            }
        }

        $product['publications'][]['publicationId'] = $onlinepublication['id'];

        if (!empty($productdata['metafields'])) {

            foreach ($productdata['metafields'] as $metafieldkey => $metafield) {
                if (is_integer($metafield['value'])) {
                    $productdata['metafields'][$metafieldkey]['value'] = (string) $metafield['value'];
                }
                $product['metafields'] = $productdata['metafields'];

            }
        }




        if (!empty($productdata['collection'])) {
            foreach ($productdata['collection'] as $collectionid => $collection) {
                $product['collectionsToJoin'][] = "gid://shopify/Collection/{$collectionid}";
            }
        }
        $productmedia = array();
        if (!empty($productdata['images'])) {
            foreach ($productdata['images'] as $imagekey => $image) {
                if(empty($image['src'])){
                    continue;
                }
                if (count($productmedia) > 249) {
                    break;
                }

                $gqimage = [];
                $gqimage['originalSource'] = $image['src'];
                $gqimage['mediaContentType'] = "IMAGE";
                if (isset($image['alt'])) {
                    $gqimage['alt'] = $image['alt'];
                }


                $productmedia[] = $gqimage;
            }
        }

        $productrawoptions = $params['product']['options'];


        $productoptions = array();
        if (!empty($productrawoptions)) {
            foreach ($productrawoptions as $optionkey => $option) {
                $productoptions[$optionkey]['name'] = $option['name'];
                $values = array();
                foreach ($option['values'] as $valuekey => $value) {
                    $values[$valuekey]['name'] = (string)$value;
                }
                $productoptions[$optionkey]['values'] = $values;

            }
        }
        $product['productOptions'] = $productoptions;

        $productquery = <<<'GRAPHQL'
            mutation CreateProduct($input: ProductInput!, $mediainput: [CreateMediaInput!]) {
            productCreate(input: $input, media: $mediainput) {
                product {
                id
                title
                images (first:250) {
                        edges {
                            node {
                            id
                            src
                            altText
                            }
                        }
                    }
                media (first:250) {
                        edges {
                            node {
                            id
                        
                            }
                        }
                    }
                options {
                    id
                    name
                    position
                    values
                    optionValues {
                        id
                        name
                        hasVariants
                    }
                }
                variants(first: 1) {
                        edges {
                            node {
                                id
                                title
                                barcode
                            }
                        }
                    }
                },
                userErrors {
                    field
                    message
                }
            }
            }
            GRAPHQL;



        $variables = [
            'input' => $product,
            'mediainput' => $productmedia,
        ];


        $productreturndata = array();

        if (1) {

            try {
                // Send GraphQL request
                $responseData = $this->graphqlQueryThalia($productquery, $variables);



                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productCreate']['userErrors']);

                } else {
                    $productId = $responseData['data']['productCreate']['product']['id'];
                    $graphqloptionids = array();
                    $currentopt = 1;

                    foreach ($responseData['data']['productCreate']['product']['options'] as $graphqloptionidkey => $option) {


                        $graphqloptionids['option' . $currentopt]['id'] = $option['id'];
                        $graphqloptionids['option' . $currentopt]['values'] = array();

                        foreach ($option['optionValues'] as $optionvalue) {
                            $graphqloptionids['option' . $currentopt]['values'][$optionvalue['name']] = $optionvalue['id'];
                        }

                        $currentopt++;
                    }

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }


        $variantsdata = array();
        foreach ($params['product']['variants'] as $rawvariantkey => $rawvariant) {


            if (!empty($rawvariant['price'])) {
                $variantdata['price'] = $rawvariant['price'];
            }

            if (!empty($variant['barcode'])) {
                $variantdata['barcode'] = isset($rawvariant['barcode']) ? $rawvariant['barcode'] : null;
            }

            if (!empty($rawvariant['taxable'])) {
                $variantdata['taxable'] = isset($rawvariant['taxable']) ? $rawvariant['taxable'] : true;
            }
            if (!empty($rawvariant['sku'])) {
                $variantdata['inventoryItem']['sku'] = $rawvariant['sku'];
            }

            if (!empty($rawvariant['cost'])) {
                $variantdata['inventoryItem']['cost'] = $rawvariant['cost'];
                $variantdata['inventoryItem']['tracked'] = true;

                if(isset($variant['inventory_management']) && $rawvariant['inventory_management'] == ''){
                    $variantdata['inventoryItem']['tracked'] = false;
                }
            }


            if (isset($rawvariant['weight'])) {

                $variantdata['inventoryItem']['measurement']['weight']['value'] = (float) $rawvariant['weight'];
                if (isset($rawvariant['weight_unit'])) {
                    switch ($rawvariant['weight_unit']) {
                        case 'lb':
                            $weight_unit = 'POUNDS';
                            break;

                        case 'kg':
                            $weight_unit = 'KILOGRAMS';
                            break;

                        default:
                            $weight_unit = 'KILOGRAMS';
                            break;
                    }

                    $variantdata['inventoryItem']['measurement']['weight']['unit'] = $weight_unit;
                }

            }



            $optionValues = array();
            if (isset($rawvariant['option1'])) {
                $optiondata['optionId'] = $graphqloptionids['option1']['id'];
                $optiondata['id'] = $graphqloptionids['option1']['values'][$rawvariant['option1']];
                $optionValues[0] = $optiondata;
            }

            if (isset($rawvariant['option2'])) {
                $optiondata['optionId'] = $graphqloptionids['option2']['id'];
                $optiondata['id'] = $graphqloptionids['option2']['values'][$rawvariant['option2']];
                $optionValues[1] = $optiondata;
            }

            if (isset($rawvariant['option3'])) {
                $optiondata['optionId'] = $graphqloptionids['option3']['id'];
                $optiondata['id'] = $graphqloptionids['option3']['values'][$rawvariant['option3']];
                $optionValues[2] = $optiondata;
            }
            $optionValues = array_values($optionValues);

            $variantdata['optionValues'] = $optionValues;
            $variantsdata[] = $variantdata;

        }












        if (1) {


            $bulkvariantquery = <<<'GRAPHQL'
                        mutation ProductVariantsCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                            productVariantsBulkCreate(
                                productId: $productId,
                                variants: $variants,
                                strategy: REMOVE_STANDALONE_VARIANT
                            ) {
                                userErrors {
                                    field
                                    message
                                }
                                product {
                                    id
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
                                productVariants {
                                    id
                                    title
                                    selectedOptions {
                                        name
                                        value
                                    }
                                }
                            }
                        }
                    GRAPHQL;

            $variables = [
                "productId" => $productId,
                "variants" => $variantsdata
            ];

        }





        try {
            // Send GraphQL request

            $responseData = $this->graphqlQueryThalia($bulkvariantquery, $variables);

            // Check for GraphQL or user errors
            if (isset($responseData['errors'])) {

                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

            } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {

                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productCreate']['userErrors']);

            } else {
                $shopifyid = str_replace("gid://shopify/Product/", "", $productId);
                sleep(5);
                return $this->graphqlGetProduct($shopifyid);
            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
        }


    }

    public function graphqlUpdateProduct($params)
    {


        $productdata = $params['product'];


        $product['id'] = $productdata['id'];
        if (strpos($productdata['id'], 'gid://shopify/Product') !== true) {
            $product['id'] = "gid://shopify/Product/{$productdata['id']}";
        }

        if (isset($productdata['title'])) {
            $product['title'] = $productdata['title'];
        }

        if (isset($productdata['body_html'])) {
            $product['descriptionHtml'] = $productdata['body_html'];
        }

        if (isset($productdata['product_type'])) {
            $product['productType'] = $productdata['product_type'];
        }

        if (isset($productdata['vendor'])) {
            $product['vendor'] = $productdata['vendor'];
        }

        if (isset($productdata['tags'])) {
            $product['tags'] = $productdata['tags'];
        }


        if (isset($productdata['template_suffix'])) {
            $product['templateSuffix'] = $productdata['template_suffix'];
        }

        if (isset($productdata['status'])) {
            $product['status'] = strtoupper($productdata['status']);
        }


        if (isset($productdata['published'])) {
            $product['published'] = $productdata['published'];
        }





        if (!empty($productdata['metafields'])) {
            foreach ($productdata['metafields'] as $metafieldkey => $metafield) {
                if (is_integer($metafield['value'])) {
                    $productdata['metafields'][$metafieldkey]['value'] = (string) $metafield['value'];
                }

                if (strpos($metafield['id'], 'gid://shopify/Metafield') !== true) {
                    $productdata['metafields'][$metafieldkey]['id'] = "gid://shopify/Metafield/" . $metafield['id'];
                }


            }

            $product['metafields'] = $productdata['metafields'];
        }

        if (!empty($productdata['collection'])) {
            foreach ($productdata['collection'] as $collectionid => $collection) {
                $product['collectionsToJoin'][] = "gid://shopify/Collection/{$collectionid}";
            }
        }


        $productmedia = array();
        if (!empty($productdata['images'])) {

            foreach ($productdata['images'] as $imagekey => $image) {
                if(empty($image['src'])){
                    continue;
                }
                if (count($productmedia) > 240) {
                    break;
                }


                $gqimage = [];
                $gqimage['originalSource'] = $image['src'];
                $gqimage['mediaContentType'] = "IMAGE";

                if (isset($image['alt'])) {
                    $gqimage['alt'] = $image['alt'];
                }

                //$gqimage['originalSource'] = $image['src'];
                $productmedia[] = $gqimage;
            }
        }

        if (!empty($productdata['options'])) {

            $productrawoptions = $productdata['options'];

            $productoptions = array();
            if (!empty($productrawoptions)) {
                foreach ($productrawoptions as $optionkey => $option) {
                    $productoptions[$optionkey]['name'] = $option['name'];
                    $values = array();
                    foreach ($option['values'] as $valuekey => $value) {
                        $values[$valuekey]['name'] = (string)$value;
                    }
                    $productoptions[$optionkey]['values'] = $values;

                }
            }



            //get options for product and cross check with payload options
            $query = <<<'GRAPHQL'
                        query getProductDetails($id: ID!) {
                                product(id: $id) {
                                    id
                                    options {
                                        id
                                        name
                                        position
                                        values
                                        optionValues {
                                            id
                                            name
                                            hasVariants
                                        }
                                    }
                                }
                            }
                    GRAPHQL;
            $productvariables['id'] = $product['id'];
            $responseData = $this->graphqlQueryThalia($query, $productvariables);
            $shopifyoptions = $responseData['data']['product']['options'];
            $missingoptions = [];




            // Find missing options
            foreach ($productoptions as $productOption) {
                foreach ($shopifyoptions as $shopifyOption) {

                    //$refOptionValues = array_column(array_column($shopifyoptions,''),'id');
                    if ($productOption['name'] === $shopifyOption['name']) {

                        $refOptionValues = array_column($shopifyOption['optionValues'], 'name');

                        // Extract Shopify option values
                        $shopifyValues = $shopifyOption['values'];
                        // Extract product option values
                        $productValues = $productOption['values'];
                        // Find the missing options
                        foreach ($productValues as $productValue) {
                            if (!in_array($productValue['name'], $refOptionValues)) {
                                $missingoptions[] = [
                                    "id" => $shopifyOption['id'],
                                    "name" => $shopifyOption['name'],
                                    "value" => $productValue['name']
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($missingoptions)) {

                $groupedByName = [];

                foreach ($missingoptions as $option) {
                    $groupedByName[$option['name']][] = $option;
                }


                foreach ($groupedByName as $byOptionName) {
                    $mutation = '
                        mutation updateOption($productId: ID!, $option: OptionUpdateInput!, $optionValuesToAdd: [OptionValueCreateInput!]) {
                        productOptionUpdate(productId: $productId, option: $option, optionValuesToAdd: $optionValuesToAdd) {
                                product {
                                    id
                                    options {
                                        id
                                        name
                                        values
                                    }
                                }
                                userErrors {
                                    field
                                    message
                                    code
                                }
                            }
                        }
                    ';

                    $variables = [
                        'productId' => $product['id'],
                        'option' => [
                            'id' => $missingoptions[0]['id'],
                        ],
                        'optionValuesToAdd' => array_map(function ($option) {
                            return [
                                'name' => $option['value']
                            ];
                        }, $byOptionName)
                    ];




                    $response = $this->graphqlQueryThalia($mutation, $variables);

                }


            }






        }








        $productquery = <<<'GRAPHQL'
                            mutation UpdateProductWithNewMedia($input: ProductInput!, $mediainput: [CreateMediaInput!]) {
                                productUpdate(input: $input, media: $mediainput) {
                                    product {
                                    id
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
                                    userErrors {
                                        field
                                        message
                                    }
                                }
                            }
                        GRAPHQL;


        $variables = [
            'input' => $product,
            'mediainput' => $productmedia,
        ];






        // Get the response body

        if (1) {

            $responseData = $this->graphqlQueryThalia($productquery, $variables);



                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['productUpdate']['userErrors']) && !empty($responseData['data']['productUpdate']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productUpdate']['userErrors']);

                } else {


                }

        }


        $query = <<<'GRAPHQL'
                        query getProductDetails($id: ID!) {
                                product(id: $id) {
                                    id
                                    options {
                                        id
                                        name
                                        position
                                        values
                                        optionValues {
                                            id
                                            name
                                            hasVariants
                                        }
                                    }
                                }
                            }
                    GRAPHQL;
        $productvariables['id'] = $product['id'];
        $responseData = $this->graphqlQueryThalia($query, $productvariables);


        //get product data 
        $graphqloptionids = array();
        $currentopt = 1;
        foreach ($responseData['data']['product']['options'] as $graphqloptionidkey => $option) {


            $graphqloptionids['option' . $currentopt]['id'] = $option['id'];
            $graphqloptionids['option' . $currentopt]['values'] = array();

            foreach ($option['optionValues'] as $optionvalue) {
                $graphqloptionids['option' . $currentopt]['values'][$optionvalue['name']] = $optionvalue['id'];
            }

            $currentopt++;
        }


        if (isset($productdata['variants'])) {

            $variants = $productdata['variants'];
            $variantsdata = [];
            $newvariantsdata = [];
            foreach ($variants as $key => $variant) {
                $variantdata = array();
                if (isset($variant['id'])) {
                    $variantdata['id'] = "gid://shopify/ProductVariant/" . $variant['id'];
                }


                if (!empty($variant['compareAtPrice'])) {
                    $variantdata['compareAtPrice'] = $variant['compareAtPrice'];
                }

                if (!empty($variant['compare_at_price'])) {
                    $variantdata['compareAtPrice'] = $variant['compare_at_price'];
                }

                if (!empty($variant['price'])) {
                    $variantdata['price'] = $variant['price'];
                }

                if (!empty($variant['barcode'])) {
                    $variantdata['barcode'] = isset($variant['barcode']) ? $variant['barcode'] : null;
                }

                if (!empty($variant['taxable'])) {
                    $variantdata['taxable'] = isset($variant['taxable']) ? $variant['taxable'] : true;
                }
                if (!empty($variant['sku'])) {
                    $variantdata['inventoryItem']['sku'] = $variant['sku'];
                }

                if (!empty($variant['cost'])) {
                    $variantdata['inventoryItem']['cost'] = $variant['cost'];
                    $variantdata['inventoryItem']['tracked'] = true;
                    if(isset($variant['inventory_management']) && $variant['inventory_management'] == ''){
                        $variantdata['inventoryItem']['tracked'] = false;
                    }
                }


                if (isset($variant['weight'])) {

                    $variantdata['inventoryItem']['measurement']['weight']['value'] = (float) $variant['weight'];
                    if (isset($variant['weight_unit'])) {
                        switch ($variant['weight_unit']) {
                            case 'lb':
                                $weight_unit = 'POUNDS';
                                break;

                            case 'kg':
                                $weight_unit = 'KILOGRAMS';
                                break;

                            default:
                                $weight_unit = 'KILOGRAMS';
                                break;
                        }

                        $variantdata['inventoryItem']['measurement']['weight']['unit'] = $weight_unit;
                    }

                }





                if (!isset($variant['id'])) {

                    $optionValues = array();
                    if (isset($variant['option1']) && isset($graphqloptionids['option1']['values'][$variant['option1']])) {
                        $optiondata['optionId'] = $graphqloptionids['option1']['id'];
                        $optiondata['id'] = $graphqloptionids['option1']['values'][$variant['option1']];
                        $optionValues[0] = $optiondata;
                    }

                    if (isset($variant['option2']) && isset($graphqloptionids['option2']['values'][$variant['option2']])) {
                        $optiondata['optionId'] = $graphqloptionids['option2']['id'];
                        $optiondata['id'] = $graphqloptionids['option2']['values'][$variant['option2']];
                        $optionValues[1] = $optiondata;
                    }

                    if (isset($variant['option3']) && isset($graphqloptionids['option2']['values'][$variant['option2']])) {
                        $optiondata['optionId'] = $graphqloptionids['option3']['id'];
                        $optiondata['id'] = $graphqloptionids['option3']['values'][$variant['option3']];
                        $optionValues[2] = $optiondata;
                    }
                    $optionValues = array_values($optionValues);

                    $variantdata['optionValues'] = $optionValues;
                    if (empty($optionValues)) {
                        continue;
                    }
                    $newvariantsdata[] = $variantdata;
                } else {



                    $variantsdata[] = $variantdata;
                }

            }


            $finalvariantvariables['productId'] = $product['id'];
            $finalvariantvariables['variants'] = $variantsdata;








            if (1) {


                $variantquery = <<<'GRAPHQL'
                    mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                        productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                            product {
                                id
                            }
                            productVariants {
                                id
                                price
                                title
                                inventoryItem{
                                    id
                                }
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                    }
                GRAPHQL;

            }

            try {
                // Send GraphQL request
                $responseData = $this->graphqlQueryThalia($variantquery, $finalvariantvariables);

                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {
                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productCreate']['userErrors']);
                } else {


                }
            } catch (\Exception $e) {
                // Handle Guzzle exceptions
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

            if (!empty($newvariantsdata)) {

                if (1) {


                    $bulkvariantquery = <<<'GRAPHQL'
                                mutation ProductVariantsCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                                    productVariantsBulkCreate(
                                        productId: $productId,
                                        variants: $variants
                                    ) {
                                        userErrors {
                                            field
                                            message
                                        }
                                        product {
                                            id
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
                                        productVariants {
                                            id
                                            title
                                            selectedOptions {
                                                name
                                                value
                                            }
                                        }
                                    }
                                }
                            GRAPHQL;

                    $variables = [
                        "productId" => $product['id'],
                        "variants" => $newvariantsdata
                    ];

                }






                $responseData = $this->graphqlQueryThalia($bulkvariantquery, $variables);



            }
        }


        $shopifyid = str_replace("gid://shopify/Product/", "", $product['id']);
        return $this->graphqlGetProduct($shopifyid);
    }

    public function graphqlGetProducts($params)
    {


        //dd($params);
        $gqparams = "";
        if (isset($params['created_at_max'])) {
            $gqparams .= " created_at:<='{$params['created_at_max']}T00:00:00Z'";
        }

        if (isset($params['created_at_min'])) {
            $gqparams .= " created_at:>='{$params['created_at_min']}T00:00:00Z'";
        }


        if (isset($params['published_status'])) {
            if ($params['published_status'] == 'published') {
                $gqparams .= " status:ACTIVE";
            } elseif ($params['published_status'] == 'unpublished') {
                $gqparams .= " status:DRAFT";
            }

        }
        if (isset($params['title'])) {
            $gqparams .= " title:*{$params['title']}*";
        }

        if (isset($params['sku'])) {
            $gqparams .= " sku:*{$params['sku']}*";
        }


        if (isset($params['handle'])) {
            $gqparams .= " handle:*{$params['handle']}*";
        }


        if (isset($params['vendor'])) {
            $gqparams .= " vendor:{$params['vendor']}";
        }

        if (isset($params['product_type'])) {
            $gqparams .= " product_type:{$params['product_type']}";
        }

        if (isset($params['since_id']) && !empty($params['since_id'])) {
            $gqparams .= " id:>{$params['since_id']}";
        }


        $cursor = '';
        if (isset($params['page_info']) && isset($params['direction']) && $params['direction'] == 'next') {
            // $gqparams .= " product_type:{$params['product_type']}";
            $cursor = 'after: "' . $params['page_info'] . '"';
        }


        if (isset($params['page_info']) && isset($params['direction']) && $params['direction'] == 'previous') {
            // $gqparams .= " product_type:{$params['product_type']}";
            $cursor = 'before: "' . $params['page_info'] . '"';
        }




        if (!isset($params['limit'])) {
            $params['limit'] = 25;
        }

        if (isset($params['ids']) && !empty($params['ids'])) {
            $params['limit'] = 250;
            $productids = explode(",", $params['ids']);
            $productIdQuery = array();
            foreach ($productids as $productid) {
                $productIdQuery[] = "(id:{$productid})";
            }
            $productIdQuery = implode(" OR ", $productIdQuery);

            $gqparams .= ' ' . $productIdQuery;
        }



        $query = array();
        $productvariants = json_encode($gqparams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productvariants = preg_replace('/"([^"]+)"\s*:/', '$1:', $productvariants);
        //$productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
        $queryinput = $productvariants;
        if (isset($params['reverse'])) {
            $reverseflag = "reverse: {$params['reverse']}";
        } else {
            $reverseflag = "";
        }
        //echo $queryinput;
        if (empty($gqparams)) {
            $gqquery = "products(first: {$params['limit']},   $reverseflag, $cursor)";
        } else {
            $gqquery = "products(first: {$params['limit']},  $reverseflag, query: $queryinput,$cursor)";
        }
        if (isset($params['direction']) && $params['direction'] == 'previous') {
            $gqquery = str_replace('first', "last", $gqquery);
        }





        $fields['id'] = 'id';
        $fields['title'] = 'title';
        $fields['handle'] = 'handle';
        $fields['status'] = 'status';
        $fields['featuredImage'] = 'featuredImage {
                                id
                                url
                            }';
        $fields['vendor'] = 'vendor';
        $fields['productType'] = 'productType';

        if (isset($params['fields'])) {
            $parmsfields = explode(",", $params['fields']);

            if (in_array('variants', $parmsfields)) {
                $fields['variants'] = 'variants(first: 250) {
                    edges {
                        node {
                        id
                        sku
                        title
                        price
                        compareAtPrice
                        taxable
                        inventoryQuantity
                        inventoryPolicy
                        createdAt
                        selectedOptions {
                                name
                                value
                            }
                        
                        inventoryItem {
                                id
                                inventoryHistoryUrl
                                measurement{
                                    id
                                    weight{
                                        unit
                                        value
                                    }
                                }
                                unitCost {
                                    amount
                                    currencyCode
                                }
                            }
                        }
                    }
                }';
            }
            if (in_array('tags', $parmsfields)) {
                $fields['tags'] = 'tags';
            }




        }
        $fields['createdAt'] = 'createdAt';
        $fields['publishedAt'] = 'publishedAt';
        $fields = implode("\n", $fields);


        $onlinepublication = [];
        $query = <<<QUERY
                query {
                    $gqquery {
                        edges {
                        node {
                            $fields
                        }
                        }
                        pageInfo {
                            hasNextPage,
                            hasPreviousPage,
                            endCursor,
                            startCursor
                        }
                    }
                }
                QUERY;

        //if filter by collectionid 
        if (isset($params['collection_id']) && !empty($params['collection_id'])) {

            $collection_id = $params['collection_id'];

            $collection_handle = $this->getCollectionHandle($collection_id);

            $query = <<<QUERY
             query {
                collectionByHandle(handle: "$collection_handle") {
                    id
                    title
                    products(first: 250) {
                    edges {
                        node {
                           $fields
                        }
                    }
                    }
                }
                }
            QUERY;
        }




        $shopifyproducts = array();
        $pageinfo = array();
        try {
            // Send GraphQL request
            $responseData = $this->graphqlQueryThalia($query);



            if (isset($responseData['data'])) {
                if (isset($params['collection_id']) && !empty($params['collection_id'])) {
                    $responseData['data']['products']['edges'] = $responseData['data']['collectionByHandle']['products']['edges'];
                }

                foreach ($responseData['data']['products']['edges'] as $key => $product) {
                    $product = $product['node'];
                    $shopifyproduct = $product;
                    $shopifyproduct['id'] = str_replace("gid://shopify/Product/", "", $shopifyproduct['id']);
                    $shopifyproduct['title'] = $product['title'];
                    $shopifyproduct['handle'] = $product['handle'];
                    $shopifyproduct['product_type'] = $product['productType'];


                    if (!empty($product['featuredImage'])) {
                        $shopifyproduct['image']['src'] = $product['featuredImage']['url'];
                    } else {
                        $shopifyproduct['image'] = null;
                    }
                    if (!empty($product['publishedAt'])) {
                        $shopifyproduct['published_at'] = $product['publishedAt'];
                    } else {
                        $shopifyproduct['published_at'] = NUll;
                    }

                    if (!empty($product['variants'])) {
                        $variants = array();
                        if (!empty($product['variants'])) {

                            foreach ($product['variants']['edges'] as $qlvariant) {


                                $variant = $qlvariant['node'];
                                $variant['product_id'] = $shopifyproduct['id'];
                                $variant['id'] = str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                                $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/", "", $variant['inventoryItem']['id']);
                                if (!empty($variant['compareAtPrice'])) {
                                    $variant['compare_at_price'] = $variant['compareAtPrice'];
                                }


                                if (!empty($variant['inventoryItem']['measurement']['weight'])) {
                                    $variant['weight_unit'] = $variant['inventoryItem']['measurement']['weight']['unit'];

                                }
                                if (!empty($variant['inventoryItem']['measurement']['weight'])) {
                                    $variant['weight'] = $variant['inventoryItem']['measurement']['weight']['value'];

                                }
                                if (!empty($variant['inventoryQuantity'])) {
                                    $variant['inventory_quantity'] = $variant['inventoryQuantity'];
                                } else {
                                    $variant['inventory_quantity'] = 0;
                                }
                                if (isset($variant['inventoryItem']['unitCost']['amount'])) {
                                    $variant['cost_price'] = $variant['inventoryItem']['unitCost']['amount'];
                                } else {
                                    $variant['cost_price'] = null;
                                }

                                //$variant['fulfillment_service'] = strtolower($variant['fulfillmentService']['serviceName']);
                                $variant['inventory_policy'] = strtolower($variant['inventoryPolicy']);
                                if (isset($variant['createdAt'])) {
                                    $variant['created_at'] = $variant['createdAt'];
                                }

                                if (isset($variant['selectedOptions'])) {
                                    foreach ($variant['selectedOptions'] as $selectedOptionskey => $selectedOption) {
                                        $optionkey = $selectedOptionskey + 1;
                                        $variant["option{$optionkey}"] = $selectedOption['value'];
                                    }

                                }
                                $variants[] = $variant;
                            }
                        }
                        $shopifyproduct['variants'] = $variants;

                    }

                    if (!empty($product['tags'])) {
                        $shopifyproduct['tags'] = implode(",", $product['tags']);
                    }

                    if (isset($product['createdAt'])) {
                        $shopifyproduct['created_at'] = $product['createdAt'];
                    }

                    $shopifyproducts[$key] = $shopifyproduct;

                }





                $responsedata['products'] = $shopifyproducts;
                if (isset($responseData['data']['products']['pageInfo'])) {
                    $pageinfo = $responseData['data']['products']['pageInfo'];
                    $responsedata['pageinfo'] = $pageinfo;
                }
                return $responsedata;
            }





            if (isset($responseData["errors"])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);

        }
    }

    public function graphqlGetProductsCount()
    {




        $query = <<<QUERY
                query {
                    productsCount {
                        count
                    }
                }
                QUERY;






        try {
            // Send GraphQL request

            $responseData = $this->graphqlQueryThalia($query);




            if (isset($responseData['data'])) {
                $responsedata = $responseData['data']['productsCount'];
                return $responsedata;
            }





            if (isset($responseData["errors"])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);

        }
    }


    public function graphqlGetProduct($shopifyid)
    {


        $shopifyid = "gid://shopify/Product/{$shopifyid}";

        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                title
                handle
                descriptionHtml 
                vendor
                productType
                tags
                status
                options {
                    name
                    values
                }
                featuredImage {
                    id
                    url
                }
                images (first:250) {
                    edges {
                        node {
                        id
                        src
                        altText
                        }
                    }
                }
                variants(first: 250) {
                    edges {
                        node {
                        id
                        sku
                        title
                        price
                        compareAtPrice
                        inventoryQuantity
                        selectedOptions {
                                name
                                value
                         }
                        inventoryItem {
                            id
                            inventoryHistoryUrl
                        }
                        
                        }
                    }
                }
            }
        }
        QUERY;








        $productreturndata = array();
        if (1) {

            try {
                // Send GraphQL request
                $responseData = $this->graphqlQueryThalia($query);


                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['product']['userErrors']);

                } elseif (empty($responseData['data']['product'])) {

                    throw new \Exception('GraphQL Error: Product Not Found');

                } else {


                    $shopifyproduct = $responseData['data']['product'];
                    $shopifyproduct['id'] = str_replace("gid://shopify/Product/", "", $shopifyproduct['id']);

                    if (!empty($shopifyproduct['featuredImage'])) {
                        $shopifyproduct['image']['src'] = $shopifyproduct['featuredImage']['url'];
                    }
                    $variants = array();
                    if (!empty($shopifyproduct['variants'])) {

                        foreach ($shopifyproduct['variants']['edges'] as $qlvariant) {

                            $variant = $qlvariant['node'];
                            $variant['id'] = str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                            $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/", "", $variant['inventoryItem']['id']);

                            if (isset($variant['selectedOptions'])) {
                                foreach ($variant['selectedOptions'] as $selectedOptionskey => $selectedOption) {
                                    $optionkey = $selectedOptionskey + 1;
                                    $variant["option{$optionkey}"] = $selectedOption['value'];
                                }

                            }

                            $variants[] = $variant;
                        }
                    }
                    $shopifyproduct['variants'] = $variants;


                    if (!empty($shopifyproduct['images'])) {
                        $shopifyimages = [];
                        foreach ($shopifyproduct['images']['edges'] as $image) {

                            $shopifyimage['id'] = str_replace("gid://shopify/ProductImage/", "", $image['node']['id']);
                            $shopifyimage['src'] = $image['node']['src'];
                            if (isset($image['node']['altText'])) {
                                $shopifyimage['alt'] = $image['node']['altText'];
                            }

                            $shopifyimages[] = $shopifyimage;

                        }
                        $shopifyproduct['images'] = $shopifyimages;
                    }



                    return $shopifyproduct;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }
    public function graphqlGetProductWithoutInventory($shopifyid)
    {


        $shopifyid = "gid://shopify/Product/{$shopifyid}";

        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                title
                handle
                descriptionHtml 
                vendor
                productType
                tags
                status
                options {
                    id
                    name
                    position
                    values
                }
                featuredImage {
                    id
                    url
                }
                images (first:250) {
                    edges {
                        node {
                        id
                        src
                        altText
                        }
                    }
                }
                variants(first: 250) {
                    edges {
                        node {
                            id
                            sku
                            title
                            selectedOptions {
                                name
                                value
                            }
                        }
                    }
                }
            }
        }
        QUERY;







        if (1) {

            try {
                // Send GraphQL request

                $responseData = $this->graphqlQueryThalia($query);


                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['product']['userErrors']);

                } elseif (empty($responseData['data']['product'])) {

                    throw new \Exception('GraphQL Error: Product Not Found');

                } else {


                    $shopifyproduct = $responseData['data']['product'];
                    $shopifyproduct['id'] = str_replace("gid://shopify/Product/", "", $shopifyproduct['id']);

                    if (!empty($shopifyproduct['featuredImage'])) {
                        $shopifyproduct['image']['src'] = $shopifyproduct['featuredImage']['url'];
                    }
                    $variants = array();
                    if (!empty($shopifyproduct['variants'])) {

                        foreach ($shopifyproduct['variants']['edges'] as $qlvariant) {

                            $variant = $qlvariant['node'];
                            $variant['id'] = str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                            if (isset($variant['selectedOptions'])) {
                                foreach ($variant['selectedOptions'] as $selectedOptionskey => $selectedOption) {
                                    $optionkey = $selectedOptionskey + 1;
                                    $variant["option{$optionkey}"] = $selectedOption['value'];
                                }

                            }
                            $variants[] = $variant;
                        }
                    }



                    $shopifyproduct['variants'] = $variants;


                    if (!empty($shopifyproduct['images'])) {
                        $shopifyimages = [];
                        foreach ($shopifyproduct['images']['edges'] as $image) {

                            $shopifyimage['id'] = str_replace("gid://shopify/ProductImage/", "", $image['node']['id']);
                            $shopifyimage['src'] = $image['node']['src'];
                            if (isset($image['node']['altText'])) {
                                $shopifyimage['alt'] = $image['node']['altText'];
                            }
                            $shopifyimages[] = $shopifyimage;

                        }
                        $shopifyproduct['images'] = $shopifyimages;
                    }

                    if (!empty($shopifyproduct['options'])) {
                        $productoptions = array();

                        foreach ($shopifyproduct['options'] as $productoption) {

                            $productoption['id'] = str_replace("gid://shopify/ProductOption/", "", $productoption['id']);
                            $productoptions[] = $productoption;

                        }
                        $shopifyproduct['options'] = $productoptions;
                    }
                    $shopifyproduct['id'] = str_replace("gid://shopify/Product/", "", $shopifyproduct['id']);


                    return $shopifyproduct;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }
    public function graphqlCreateProductImage(array $mediaItems, int $shopifyProductId)
    {
        $shopifyProductIdGid = "gid://shopify/Product/{$shopifyProductId}";

        $mediaInput = array_map(function ($item) {
            $alt = addslashes($item['alt']);
            $mediaType = 'IMAGE';
            $source = addslashes($item['url']);

            return <<<ITEM
            {
                alt: "$alt",
                mediaContentType: $mediaType,
                originalSource: "$source"
            }
            ITEM;
        }, $mediaItems);

        $mediaList = implode(',', $mediaInput);

        $query = <<<QUERY
                mutation {
                    productCreateMedia(
                        media: [$mediaList],
                        productId: "$shopifyProductIdGid"
                    ) {
                        media {
                            id
                            alt
                            mediaContentType
                            status
                            preview {
                                image {
                                    url
                                    id
                                }
                            }
                        }
                        mediaUserErrors {
                            field
                            message
                        }
                        product {
                            id
                            title
                        }
                    }
                }
                QUERY;

        $responseData = $this->graphqlQueryThalia($query);

        try {
            if (isset($responseData['errors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
            } elseif (isset($responseData['data']['productDeleteMedia']['mediaUserErrors']) && !empty($responseData['data']['productDeleteMedia']['mediaUserErrors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productDeleteMedia']['mediaUserErrors']);
            } else {
                $responseData['data']['productCreateMedia']['media'] = array_map(function ($item) {
                    $item['id'] = str_replace("gid://shopify/MediaImage/", "", $item['id']);
                    return $item;
                }, $responseData['data']['productCreateMedia']['media']);

                return ['images' => $responseData['data']['productCreateMedia']['media']];
            }
        } catch (\Exception $e) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, [], $e);
        }
    }
    public function graphqlDeleteProductImage(array $imageIds, int $shopifyProductId)
    {
        $shopifyProductIdGid = "gid://shopify/Product/{$shopifyProductId}";

        $mediaIdStrings = array_map(function ($id) {
            return "\"gid://shopify/MediaImage/{$id}\"";
        }, $imageIds);
        $mediaIdList = implode(',', $mediaIdStrings);

        $query = <<<QUERY
        mutation {
        productDeleteMedia(
            productId: "$shopifyProductIdGid",
            mediaIds: [$mediaIdList]
        ) {
            deletedMediaIds
            deletedProductImageIds
            mediaUserErrors {
                field
                message
            }
            product {
                id
                title
                media(first: 5) {
                    nodes {
                        alt
                        mediaContentType
                        status
                    }
                }
            }
        }
    }
    QUERY;

        $responseData = $this->graphqlQueryThalia($query);

        try {
            if (isset($responseData['errors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);
            } elseif (isset($responseData['data']['productDeleteMedia']['mediaUserErrors']) && !empty($responseData['data']['productDeleteMedia']['mediaUserErrors'])) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productDeleteMedia']['mediaUserErrors']);
            } else {
                $responseData['data']['productDeleteMedia']['deletedMediaIds'] = array_map(function ($item) {
                    $item = str_replace("gid://shopify/MediaImage/", "", $item);
                    return $item;
                }, $responseData['data']['productDeleteMedia']['deletedMediaIds']);

                return ['deletedImages' => $responseData['data']['productDeleteMedia']['deletedMediaIds']];
            }
        } catch (\Exception $e) {
            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, [], $e);
        }
    }
    public function graphqlDeleteProduct($shopifyid)
    {


        $shopifyid = "gid://shopify/Product/{$shopifyid}";

        $query = <<<QUERY
        mutation {
            productDelete(input: {id: "$shopifyid"}) {
                deletedProductId
                userErrors {
                field
                message
                }
            }
        }
        QUERY;








        $productreturndata = array();
        if (1) {

            try {

                $responseData = $this->graphqlQueryThalia($query);


                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['product']['userErrors']);

                } else {

                    return $responseData;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }
    public function graphqlDeleteVariant($shopifyid, $variantid)
    {




        $query = <<<QUERY
        mutation DeleteProductVariant {
            productVariantsBulkDelete(productId: "gid://shopify/Product/$shopifyid", variantsIds: ["gid://shopify/ProductVariant/$variantid"]) {
                product {
                id
                title
                }
                userErrors {
                field
                message
                }
            }
        }
        QUERY;








        $productreturndata = array();
        if (1) {

            try {
                // Send GraphQL request
                $responseData = $this->graphqlQueryThalia($query);



                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['productVariantsBulkDelete']['userErrors']) && !empty($responseData['data']['productVariantsBulkDelete']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariantsBulkDelete']['userErrors']);

                } else {

                    return $responseData;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }



    public function graphqlGetProductVariants($shopifyid)
    {


        $shopifyid = "gid://shopify/Product/{$shopifyid}";

        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                variants(first: 250) {
                    edges {
                        node {
                        id
                        sku
                        title
                        inventoryItem {
                            id
                            inventoryHistoryUrl
                        
                            }
                        }
                    }
                }
            }
        }
        QUERY;








        $productreturndata = array();
        if (1) {

            try {

                $responseData = $this->graphqlQueryThalia($query);

                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['product']['userErrors']);

                } else {
                    $shopifyproduct = $responseData['data']['product'];
                    $shopifyproduct['id'] = str_replace("gid://shopify/Product/", "", $shopifyproduct['id']);

                    if (!empty($shopifyproduct['featuredImage'])) {
                        $shopifyproduct['image']['src'] = $shopifyproduct['featuredImage']['url'];
                    }
                    if (!empty($shopifyproduct['variants'])) {
                        $variants = array();
                        foreach ($shopifyproduct['variants']['edges'] as $qlvariant) {

                            $variant = $qlvariant['node'];
                            $variant['id'] = str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                            $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/", "", $variant['inventoryItem']['id']);
                            unset($variant['inventoryItem']);
                            $variants[] = $variant;
                        }
                    }


                    return $variants;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }
    public function graphqlGetVariant($variantid)
    {




        $query = <<<QUERY
            query {
                productVariant(id: "gid://shopify/ProductVariant/$variantid") {
                    id
                    title
                    displayName
                    createdAt
                    price
                    compareAtPrice
                    inventoryQuantity
                    availableForSale
                    barcode
                    inventoryItem {
                        id
                        inventoryHistoryUrl
                        sku
                         measurement{
                            id
                            weight{
                                unit
                                value
                            }
                        }
                    }
                }
            }
        QUERY;






        if (1) {

            try {

                $responseData = $this->graphqlQueryThalia($query);

                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['productVariant']['userErrors']) && !empty($responseData['data']['productVariant']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariant']['userErrors']);

                } else {
                    $shopifyvariant = $responseData['data']['productVariant'];



                    $variant = $shopifyvariant;

                    $variant['id'] = str_replace("gid://shopify/ProductVariant/", "", $variant['id']);
                    $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/", "", $variant['inventoryItem']['id']);
                    unset($variant['inventoryItem']);



                    return $variant;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }


    public function graphqlCheckProductOnShopify($shopifyid)
    {


        $shopifyid = "gid://shopify/Product/{$shopifyid}";

        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                status
            }
        }
        QUERY;







        $productreturndata = array();
        if (1) {

            try {
                // Send GraphQL request
                $responseData = $this->graphqlQueryThalia($query);


                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {


                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData["errors"]);

                } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {

                    throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['product']['userErrors']);

                } else {
                    $shopifyproduct = $responseData['data']['product'];





                    return $shopifyproduct;

                }
            } catch (\Exception $e) {
                throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400,[],$e);
            }

        }
    }

    public function getCollectionHandle($collection_id)
    {


        $query = <<<QUERY
        query {
                    collection(id: "gid://shopify/Collection/$collection_id") {
                        id
                        title
                        handle
                        updatedAt
                    }
                }
        QUERY;


        $responseData = $this->graphqlQueryThalia($query);

        if (isset($responseData['data']['collection']['id'])) {
            return $responseData['data']['collection']['handle'];
        } else {
            throw new \Exception('GraphQL Errors: Collection Not Found');
        }
    }

    public function graphQLQuery($query, $shop, $accessToken)
    {
        $query = <<<QUERY
                    $query
                QUERY;



        $client = new Client([
            'base_uri' => "https://$shop/admin/api/2025-01/",
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken
            ]
        ]);

        $response = $client->post('graphql.json', [
            'body' => json_encode(['query' => $query])
        ]);

        // Get the response body
        $body = $response->getBody();
        $responseData = json_decode($body, true);

        return $responseData;
    }

    public function reOrderProductImages($params)
    {



        $productid = $params['product']['id'];


        $mediaquery = <<<QUERY
                    query productq {
                    product(id: "gid://shopify/Product/$productid") {
                        images (first:250) {
                            edges {
                                node {
                                id
                                src
                                }
                            }
                        }
                        media(first: 250) {
                            edges {
                            node {
                                id
                                ... on MediaImage {
                                id
                                image {
                                    id
                                    src
                                }
                               
                                }
                            }
                            }
                        }
                        
                    }
                }
                QUERY;



        $responseData = $this->graphqlQueryThalia($mediaquery);

        $shopifyimages = array();
        foreach ($responseData['data']['product']['images']['edges'] as $key => $image) {
            $imageid = str_replace('gid://shopify/ProductImage/', '', $image['node']['id']);
            $imagesrc = $image['node']['src'];

            $shopifyimages[$imagesrc] = $imageid;
        }



        $productmedia = array();
        foreach ($responseData['data']['product']['media']['edges'] as $key => $media) {

            $mediaid = str_replace('gid://shopify/MediaImage/', '', $media['node']['id']);

            if (isset($media['node']['image']['id'])) {
                // $imageid = str_replace('gid://shopify/ImageSource/','',$media['node']['image']['id']); //$media['node']['image']['id'];
                // $productmedia[$imageid] = $mediaid;
                $mediaurl = $media['node']['image']['src'];

                $tmpimageid = $shopifyimages[$mediaurl];
                $productmedia[$tmpimageid] = $mediaid;
            }

        }








        $productorder = array();
        foreach ($params['product']['images'] as $key => $image) {
            $productorder[$image['position']] = '{ id: "gid://shopify/MediaImage/' . $productmedia[$image['id']] . '", newPosition: "' . $image['position'] . '" }';

        }
        ksort($productorder);
        $productorder = implode(",", $productorder);


        $query = <<<QUERY
                    mutation ReorderProductMedia {
                        productReorderMedia(id: "gid://shopify/Product/$productid", moves: [
                       $productorder
                        ]) {
                        job {
                            id
                        }
                        mediaUserErrors {
                            field
                            message
                        }
                        }
                    }
                QUERY;



        $responseData = $this->graphqlQueryThalia($query);

        return $responseData;

    }


    public function getProductIdFromVairant($variantid): array
    {




        $query = <<<QUERY
        query GetProductFromVariant {
            productVariant(id: "gid://shopify/ProductVariant/$variantid") {
                id
                title
                price
                product {
                id
                }
            }
            }
        QUERY;



        $responseData = $this->graphqlQueryThalia($query);

        if (isset($responseData['data']['productVariant'])) {
            $product['product_id'] = str_replace("gid://shopify/Product/", "", $responseData['data']['productVariant']['product']['id']);
            return $product;

        } else {
            throw new \Exception('GraphQL Errors: productVariant Not Found');
        }
    }

    public function graphqlUpdateVariant($shopifyId, $variantId, $params)
    {
        $variant = $params['variant'];


        $productId = $shopifyId;
        if (strpos($shopifyId, 'gid://shopify/Product') !== true) {
            $productId = "gid://shopify/Product/{$shopifyId}";
        }



        if (strpos($variantId, 'gid://shopify/ProductVariant') !== true) {
            $variantId = "gid://shopify/ProductVariant/{$variantId}";
        }

        $variantdata['id'] = $variantId;

        if (!empty($variant['price'])) {
            $variantdata['price'] = $variant['price'];
        }

        if (!empty($variant['compare_at_price'])) {
            $variantdata['compareAtPrice'] = $variant['compare_at_price'];
        }

        if (!empty($variant['barcode'])) {
            $variantdata['barcode'] = $variant['barcode'];
        }


        if (!empty($variant['taxable'])) {
            $variantdata['taxable'] = isset($variant['taxable']) ? $variant['taxable'] : true;
        }







        if (!empty($variant['cost'])) {
            $variantdata['inventoryItem']['cost'] = $variant['cost'];
            $variantdata['inventoryItem']['tracked'] = true;
            if(isset($variant['inventory_management']) && $variant['inventory_management'] == ''){
                 $variantdata['inventoryItem']['tracked'] = false;
            }
        }

        if (!empty($variant['sku'])) {
            $variantdata['inventoryItem']['sku'] = $variant['sku'];
        }

        if (isset($variant['weight'])) {

            $variantdata['inventoryItem']['measurement']['weight']['value'] = (float) $variant['weight'];
            if (isset($variant['weight_unit'])) {
                switch ($variant['weight_unit']) {
                    case 'lb':
                        $weight_unit = 'POUNDS';
                        break;

                    case 'kg':
                        $weight_unit = 'KILOGRAMS';
                        break;

                    default:
                        $weight_unit = 'KILOGRAMS';
                        break;
                }

                $variantdata['inventoryItem']['measurement']['weight']['unit'] = $weight_unit;
            }

        }


        $finalvariantvariables['productId'] = $productId;
        $finalvariantvariables['variants'][] = $variantdata;



        $variantquery = <<<'GRAPHQL'
        mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
        productVariantsBulkUpdate(productId: $productId, variants: $variants) {
            product {
                id
            }
            productVariants {
                id
                title
                inventoryItem{
                    id
                }
            }
            userErrors {
                field
                message
            }
        }
        }
        GRAPHQL;

        $responseData = $this->graphqlQueryThalia($variantquery, $finalvariantvariables);

        if (isset($responseData['data']['productVariantsBulkUpdate']['userErrors']) && !empty($responseData['data']['productVariantsBulkUpdate']['userErrors'])) {

            throw new GraphqlException('GraphQL Error: ' . $this->shopDomain, 400, $responseData['data']['productVariantsBulkUpdate']['userErrors']);

        } else {
            $responseData = $responseData['data']['productVariantsBulkUpdate'];
        }


        return $responseData;
    }

    public function debugPM($data): void
    {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        exit;
    }

    

}