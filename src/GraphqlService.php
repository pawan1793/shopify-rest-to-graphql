<?php

namespace Thalia\ShopifyRestToGraphql;

use GuzzleHttp\Client;

class GraphqlService
{   

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


    public function graphqlPostProduct($params, $shop,$accessToken)
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

        $client = new Client([
            'base_uri' => "https://$shop/admin/api/2024-04/",
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken
            ]
        ]);

        try {
            // Send GraphQL request
            $response = $client->post('graphql.json', [
                'body' => json_encode(['query' => $query])
            ]);

            // Get the response body
            $body = $response->getBody();
            $responseData = json_decode($body, true);
           
          
            if (isset($responseData["errors"])) {
                throw new \Exception('GraphQL Errors: ' . print_r($responseData["errors"], true));
            }else {
                foreach ($responseData["data"]["publications"]["edges"] as $key => $publication) {
                    if($publication["node"]["name"] == "Online Store"){
                        $onlinepublication = $publication["node"];
                    }
                  
                }
               
            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            echo 'Request Error: ';

        }    


       

        $productdata = $params['product'];

        $product['title'] = $productdata['title'];
        $product['bodyHtml'] = $productdata['body_html'];
        $product['productType'] = $productdata['product_type'];
        $product['vendor'] = $productdata['vendor'];
        $product['tags'] = $productdata['tags'];
        $product['templateSuffix'] = $productdata['template_suffix'];
      
        $product['publications'][]['publicationId'] = $onlinepublication['id'];
       

        foreach ($productdata['metafields'] as $metafieldkey => $metafield)
        {
            if (is_integer($metafield['value']))
            {
                $productdata['metafields'][$metafieldkey]['value'] = (string)$metafield['value'];
            }
            $product['metafields'] = $productdata['metafields'];
           
        }

        if (!empty($productdata['collection']))
        {
            foreach ($productdata['collection'] as $collectionid => $collection)
            {
                $product['collectionsToJoin'][] = "gid://shopify/Collection/{$collectionid}";
            }
        }
        $productmedia = array();
        if (!empty($productdata['images']))
        {
            foreach ($productdata['images'] as $imagekey => $image)
            {
                $gqimage = [];
                $gqimage['originalSource'] = $image['src'];
                $gqimage['mediaContentType'] = "MEDIAPMIMAGE";
                //$gqimage['originalSource'] = $image['src'];
                $productmedia[] = $gqimage;
            }
        }
      

      
        $productDataJson = json_encode($product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productDataJson = preg_replace('/"([^"]+)"\s*:/', '$1:', $productDataJson);
        $input = $productDataJson;

        $productmedia = json_encode($productmedia, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productmedia = preg_replace('/"([^"]+)"\s*:/', '$1:', $productmedia);
        $productmedia = str_replace('"MEDIAPMIMAGE"', "IMAGE", $productmedia);
        $mediainput = $productmedia;

        $productquery = <<<GQL
        mutation {
          productCreate(input: $input,media: $mediainput) {
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
            },
             userErrors {
              field
              message
            }
          }
        }
        GQL;
        

  
        


            // Initialize Guzzle client
            $client = new Client([
                'base_uri' => "https://$shop/admin/api/2024-04/",
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $accessToken
                ]
            ]);

           $productreturndata = array();
            if(1){
           
                try {
                    // Send GraphQL request
                    $response = $client->post('graphql.json', [
                        'body' => json_encode(['query' => $productquery])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                   
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                        echo 'GraphQL Errors: ' . print_r($responseData['errors'], true);
                    } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                        echo 'User Errors: ' . print_r($responseData['data']['productCreate']['userErrors'], true);
                    } else {
                        // Print the created product details
                        $variantid = $responseData['data']['productCreate']['product']['variants']['edges'][0]['node']['id'];
                     

                        $productId = $responseData['data']['productCreate']['product']['id'];

                        $productreturndata['id'] = str_replace("gid://shopify/Product/","", $productId);
                        $productreturndata['variants'][0]['id'] = str_replace("gid://shopify/ProductVariant/","", $variantid);

                    }
                } catch (\Exception $e) {
                    // Handle Guzzle exceptions
                    echo 'Request Error: ' . $e->getMessage();
                }

            }
          
            $variant = $productdata['variants'][0];
       
            $variantdata['id'] = $variantid;
            $variantdata['price'] = $variant['price'];
            if(!empty($variant['compare_at_price'])){
                $variantdata['compareAtPrice'] = $variant['compare_at_price'];
            }
            $variantdata['barcode'] = $variant['barcode'];
            $variantdata['sku'] = $variant['sku'];
            $variantdata['taxable'] = $variant['taxable'];
            $variantdata['weight'] = (float)$variant['weight'];
            $variantdata['inventoryQuantities'][0]['availableQuantity'] = 1245;
            $variantdata['inventoryQuantities'][0]['locationId'] = "gid://shopify/Location/60977545293";
            $variantdata['fulfillmentServiceId'] = 'gid://shopify/FulfillmentService/manual';
            if(!empty($variant['cost'])){
                $variantdata['inventoryItem']['cost'] =  $variant['cost'];
                $variantdata['inventoryItem']['tracked'] = true;
            }
           
            
    
        
         
            $productvariants = json_encode($variantdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
            $productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
            $variantsinput = $productvariants;
                
           
                
           


            if(1){
           
               
                $query = <<<GQL
                mutation {
                  productVariantUpdate(input: $variantsinput) {
                    productVariant {
                      id
                      title
                      inventoryItem{
                        id
                      }
                    },
                     userErrors {
                      field
                      message
                    }
                  }
                }
                GQL;
          
            }


            
            // Initialize Guzzle client
            $client = new Client([
                'base_uri' => "https://$shop/admin/api/2024-04/",
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $accessToken
                ]
            ]);

            try {
                // Send GraphQL request
                $response = $client->post('graphql.json', [
                    'body' => json_encode(['query' => $query])
                ]);

                // Get the response body
                $body = $response->getBody();
                $responseData = json_decode($body, true);
              
                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {
                    echo 'GraphQL Errors: ' . print_r($responseData['errors'], true);
                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                    echo 'User Errors: ' . print_r($responseData['data']['productCreate']['userErrors'], true);
                } else {
                    // Print the created product details
                    $inventory_item_id = $responseData['data']['productVariantUpdate']['productVariant']['inventoryItem']['id'];
                    
                    $inventory_item_id = str_replace("gid://shopify/InventoryItem/","",$inventory_item_id);
                    $productreturndata['variants'][0]['inventory_item_id'] = $inventory_item_id;
                    //inventory_item_id
                   
                }
            } catch (\Exception $e) {
                // Handle Guzzle exceptions
                echo 'Request Error: ' . $e->getMessage();
            }
          
          return $productreturndata;
    }

    public function graphqlUpdateProduct($params,  $shop,$accessToken)
    {

      
      
       
       

        $productdata = $params['product'];
      
        $product['id'] = $productdata['id'];
        $product['title'] = $productdata['title'];
        $product['bodyHtml'] = $productdata['body_html'];
        $product['productType'] = $productdata['product_type'];
        $product['vendor'] = $productdata['vendor'];
        $product['tags'] = $productdata['tags'];
        $product['templateSuffix'] = $productdata['template_suffix'];
      
       
        if(!empty($productdata['metafields'])){
            foreach ($productdata['metafields'] as $metafieldkey => $metafield)
            {
                if (is_integer($metafield['value']))
                {
                    $productdata['metafields'][$metafieldkey]['value'] = (string)$metafield['value'];
                }

               
            }

            $product['metafields'] = $productdata['metafields'];
        }

        if (!empty($productdata['collection']))
        {
            foreach ($productdata['collection'] as $collectionid => $collection)
            {
                $product['collectionsToJoin'][] = "gid://shopify/Collection/{$collectionid}";
            }
        }
        $productmedia = array();
        if (!empty($productdata['images']))
        {
            foreach ($productdata['images'] as $imagekey => $image)
            {
                $gqimage = [];
                $gqimage['originalSource'] = $image['src'];
                $gqimage['mediaContentType'] = "MEDIAPMIMAGE";
                //$gqimage['originalSource'] = $image['src'];
                $productmedia[] = $gqimage;
            }
        }
      

      
        $productDataJson = json_encode($product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productDataJson = preg_replace('/"([^"]+)"\s*:/', '$1:', $productDataJson);
        $input = $productDataJson;

        $productmedia = json_encode($productmedia, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productmedia = preg_replace('/"([^"]+)"\s*:/', '$1:', $productmedia);
        $productmedia = str_replace('"MEDIAPMIMAGE"', "IMAGE", $productmedia);
        $mediainput = $productmedia;

        $productquery = <<<GQL
        mutation {
          productUpdate(input: $input,media: $mediainput) {
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
            },
             userErrors {
              field
              message
            }
          }
        }
        GQL;
        

  
        

       // $variantid = "gid://shopify/ProductVariant/41121605681229";
      

      

            // Initialize Guzzle client
            $client = new Client([
                'base_uri' => "https://$shop/admin/api/2024-04/",
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $accessToken
                ]
            ]);

           $productreturndata = array();
            if(1){
           
                try {
                    // Send GraphQL request
                    $response = $client->post('graphql.json', [
                        'body' => json_encode(['query' => $productquery])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                        echo 'GraphQL Errors: ' . print_r($responseData['errors'], true);
                    } elseif (isset($responseData['data']['productUpdate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                        echo 'User Errors: ' . print_r($responseData['data']['productCreate']['userErrors'], true);
                    } else {
                        // Print the created product details
                        $variantid = $responseData['data']['productUpdate']['product']['variants']['edges'][0]['node']['id'];
                     

                        $productId = $responseData['data']['productUpdate']['product']['id'];

                        $productreturndata['id'] = str_replace("gid://shopify/Product/","", $productId);
                        $productreturndata['variants'][0]['id'] = str_replace("gid://shopify/ProductVariant/","", $variantid);

                    }
                } catch (\Exception $e) {
                    // Handle Guzzle exceptions
                    echo 'Request Error: ' . $e->getMessage();
                }

            }
           
            //Product Created: Array ( [id] => gid://shopify/Product/7267875356749 [title] => Gather Productivity Kit (Black/Walnut) [variants] => Array ( [edges] => Array ( [0] => Array ( [node] => Array ( [id] => gid://shopify/ProductVariant/41124560502861 [title] => Default Title [barcode] => ) ) ) ) )

            $variant = $productdata['variants'][0];
       
            $variantdata['id'] = $variantid;
            $variantdata['price'] = $variant['price'];
            if(!empty($variant['compare_at_price'])){
                $variantdata['compareAtPrice'] = $variant['compare_at_price'];
            }
            $variantdata['barcode'] = $variant['barcode'];
            $variantdata['sku'] = $variant['sku'];
            $variantdata['taxable'] = $variant['taxable'];
            $variantdata['weight'] = (float)$variant['weight'];
            $variantdata['inventoryQuantities'][0]['availableQuantity'] = 1245;
            $variantdata['inventoryQuantities'][0]['locationId'] = "gid://shopify/Location/60977545293";
            $variantdata['fulfillmentServiceId'] = 'gid://shopify/FulfillmentService/manual';
            if(!empty($variant['cost'])){
                $variantdata['inventoryItem']['cost'] =  $variant['cost'];
                $variantdata['inventoryItem']['tracked'] = true;
            }
           
            
    
        
         
            $productvariants = json_encode($variantdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
            $productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
            $variantsinput = $productvariants;
                
           
                
           


            if(1){
           
               
                $query = <<<GQL
                mutation {
                  productVariantUpdate(input: $variantsinput) {
                    productVariant {
                      id
                      title
                      inventoryItem{
                        id
                      }
                    },
                     userErrors {
                      field
                      message
                    }
                  }
                }
                GQL;
          
            }


            
            // Initialize Guzzle client
            $client = new Client([
                'base_uri' => "https://$shop/admin/api/2024-04/",
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $accessToken
                ]
            ]);

            try {
                // Send GraphQL request
                $response = $client->post('graphql.json', [
                    'body' => json_encode(['query' => $query])
                ]);

                // Get the response body
                $body = $response->getBody();
                $responseData = json_decode($body, true);
              
                // Check for GraphQL or user errors
                if (isset($responseData['errors'])) {
                    echo 'GraphQL Errors: ' . print_r($responseData['errors'], true);
                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                    echo 'User Errors: ' . print_r($responseData['data']['productCreate']['userErrors'], true);
                } else {
                    // Print the created product details
                    $inventory_item_id = $responseData['data']['productVariantUpdate']['productVariant']['inventoryItem']['id'];
                    
                    $inventory_item_id = str_replace("gid://shopify/InventoryItem/","",$inventory_item_id);
                    $productreturndata['variants'][0]['inventory_item_id'] = $inventory_item_id;
                    //inventory_item_id
                   
                }
            } catch (\Exception $e) {
                // Handle Guzzle exceptions
                echo 'Request Error: ' . $e->getMessage();
            }
          
          return $productreturndata;
    }
}
