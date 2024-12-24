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
        $response = $client->post('graphql.json', [
            'body' => json_encode(['query' => $query])
        ]);

        // Get the response body
        $body = $response->getBody();
        $responseData = json_decode($body, true);
        
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
            throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));

        }    


       

        $productdata = $params['product'];

        $product['title'] = $productdata['title'];
        $product['bodyHtml'] = $productdata['body_html'];
        $product['productType'] = $productdata['product_type'];
        $product['vendor'] = $productdata['vendor'];
        $product['tags'] = $productdata['tags'];
        $product['templateSuffix'] = $productdata['template_suffix'];

        if(isset( $productdata['published'])){
            if( $productdata['published'] == false){
                $product['status'] = 'DRAFT';
            }
        }
      
        $product['publications'][]['publicationId'] = $onlinepublication['id'];
        
        if(!empty($productdata['metafields'])){
            
            foreach ($productdata['metafields'] as $metafieldkey => $metafield)
            {
                if (is_integer($metafield['value']))
                {
                    $productdata['metafields'][$metafieldkey]['value'] = (string)$metafield['value'];
                }
                $product['metafields'] = $productdata['metafields'];
            
            }
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
        $productDataJson = str_replace('status:"DRAFT"', "status:DRAFT", $productDataJson);
      
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
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productCreate']['userErrors'], true));

                    } else {
                        // Print the created product details
                        $variantid = $responseData['data']['productCreate']['product']['variants']['edges'][0]['node']['id'];
                     

                        $productId = $responseData['data']['productCreate']['product']['id'];

                        $productreturndata['id'] = str_replace("gid://shopify/Product/","", $productId);
                        $productreturndata['variants'][0]['id'] = str_replace("gid://shopify/ProductVariant/","", $variantid);

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
            
            if(!isset($variantid)){
                return false;
            }

            $variant = $productdata['variants'][0];
            

            $variantdata['id'] = $variantid;
            $variantdata['price'] = $variant['price'];
            if(!empty($variant['compare_at_price'])){
                $variantdata['compareAtPrice'] = $variant['compare_at_price'];
            }
            $variantdata['barcode'] = $variant['barcode'];
            $variantdata['sku'] = $variant['sku'];
            $variantdata['taxable'] = isset($variant['taxable']) ? $variant['taxable'] :  true;
            if(isset($variant['weight'])){
                $variantdata['weight'] = (float)$variant['weight'];
            }
            
            
            $variantdata['inventoryManagement'] = 'SHOPIFY';

            //$variantdata['inventoryQuantities'][0]['availableQuantity'] = 1245;
            // $variantdata['inventoryQuantities'][0]['locationId'] = "gid://shopify/Location/60977545293";
            // $variantdata['fulfillmentServiceId'] = 'gid://shopify/FulfillmentService/manual';
          
            if(!empty($variant['cost'])){
                $variantdata['inventoryItem']['cost'] =  $variant['cost'];
                $variantdata['inventoryItem']['tracked'] = true;
            }
           
            
    
        
         
            $productvariants = json_encode($variantdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
            $productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
            $productvariants = str_replace('inventoryManagement:"SHOPIFY"','inventoryManagement:SHOPIFY',$productvariants);
            
          
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
                    
                    throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                   
                    throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productCreate']['userErrors'], true));

                } else {
                    // Print the created product details
                    $inventory_item_id = $responseData['data']['productVariantUpdate']['productVariant']['inventoryItem']['id'];
                    
                    $inventory_item_id = str_replace("gid://shopify/InventoryItem/","",$inventory_item_id);
                    $productreturndata['variants'][0]['inventory_item_id'] = $inventory_item_id;
                    //inventory_item_id
                   
                }
            } catch (\Exception $e) {
                // Handle Guzzle exceptions
                throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
            }
          
          return $productreturndata;
    }

    public function graphqlPostProductWithVariants($params, $shop,$accessToken)
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
        $response = $client->post('graphql.json', [
            'body' => json_encode(['query' => $query])
        ]);

        // Get the response body
        $body = $response->getBody();
        $responseData = json_decode($body, true);
        
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
            throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));

        }    


       

        $productdata = $params['product'];

        $product['title'] = $productdata['title'];
        $product['bodyHtml'] = $productdata['body_html'];
        $product['productType'] = $productdata['product_type'];
        $product['vendor'] = $productdata['vendor'];
        $product['tags'] = $productdata['tags'];
        $product['templateSuffix'] = $productdata['template_suffix'];

        if(isset( $productdata['published'])){
            if( $productdata['published'] == false){
                $product['status'] = 'DRAFT';
            }
        }
      
        $product['publications'][]['publicationId'] = $onlinepublication['id'];
        
        if(!empty($productdata['metafields'])){
            
            foreach ($productdata['metafields'] as $metafieldkey => $metafield)
            {
                if (is_integer($metafield['value']))
                {
                    $productdata['metafields'][$metafieldkey]['value'] = (string)$metafield['value'];
                }
                $product['metafields'] = $productdata['metafields'];
            
            }
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
       
        $productrawoptions  = $params['product']['options'];
        $productoptions = array();
        if(!empty($productrawoptions)){
            foreach($productrawoptions as $optionkey => $option){
                $productoptions[$optionkey]['name'] = $option['name'];
                $values = array();
                foreach ($option['values'] as $valuekey => $value) {
                    $values[$valuekey]['name'] = $value;
                }
                $productoptions[$optionkey]['values'] = $values;
                
            }
        }
        $product['productOptions'] = $productoptions;
        $productDataJson = json_encode($product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productDataJson = preg_replace('/"([^"]+)"\s*:/', '$1:', $productDataJson);
        $productDataJson = str_replace('status:"DRAFT"', "status:DRAFT", $productDataJson);
      
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
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productCreate']['userErrors'], true));

                    } else {
                       $productId = $responseData['data']['productCreate']['product']['id'];
                       $graphqloptionids  = array();
                       $currentopt = 1;
                        foreach ($responseData['data']['productCreate']['product']['options'] as $graphqloptionidkey => $option) {
                           
                        
                            $graphqloptionids['option'.$currentopt]['id'] = $option['id'];
                            $graphqloptionids['option'.$currentopt]['values'] = array();
                           
                            foreach($option['optionValues'] as $optionvalue){
                                $graphqloptionids['option'.$currentopt]['values'][$optionvalue['name']] = $optionvalue['id'];
                            }

                            $currentopt++;
                        }

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
            
           
            $variantsdata = array();
            foreach($params['product']['variants'] as $rawvariantkey => $rawvariant){
                //$variantdata['id'] = $rawvariant;
                $variantdata['price'] = $rawvariant['price'];
                if(!empty($variant['compare_at_price'])){
                    $variantdata['compareAtPrice'] = $rawvariant['compare_at_price'];
                }
                $variantdata['barcode'] = $rawvariant['barcode'];
                //$variantdata['sku'] = $rawvariant['sku'];
                $variantdata['taxable'] = isset($variant['taxable']) ? $rawvariant['taxable'] :  true;
                if(isset($variant['weight'])){
                    $variantdata['weight'] = (float)$rawvariant['weight'];
                }
                
                
                $optionValues = array();
                if(isset($rawvariant['option1'])){
                    $optiondata['optionId'] = $graphqloptionids['option1']['id'];
                    $optiondata['id'] = $graphqloptionids['option1']['values'][$rawvariant['option1']];
                    $optionValues[0] = $optiondata;
                }

                if(isset($rawvariant['option2'])){
                    $optiondata['optionId'] = $graphqloptionids['option2']['id'];
                    $optiondata['id'] = $graphqloptionids['option2']['values'][$rawvariant['option2']];
                    $optionValues[1] = $optiondata;
                }

                if(isset($rawvariant['option3'])){
                    $optiondata['optionId'] = $graphqloptionids['option3']['id'];
                    $optiondata['id'] = $graphqloptionids['option3']['values'][$rawvariant['option3']];
                    $optionValues[2] = $optiondata;
                }
                $optionValues = array_values($optionValues);

                $variantdata['optionValues'] = $optionValues;
                $variantsdata[] = $variantdata;
               
            }

            // "productId": "gid://shopify/Product/9704751497507",
            //print_r($variantsdata);
            //$variantBULk['productId'] = $productId;
            $variantBULk = $variantsdata;
           
           
        
         
            $productvariants = json_encode($variantBULk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
            $productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
            $productvariants = str_replace('inventoryManagement:"SHOPIFY"','inventoryManagement:SHOPIFY',$productvariants);
            $variantsinput = $productvariants;
                 
           
                
           


            if(1){
           
                // mutation {
                //     productCreate(input: $input,media: $mediainput) {
                print_r($productId);
                print_r($variantsinput);
                $query = <<<GQL
                mutation {
                productVariantsBulkCreate(
                    productId: "$productId",
                    variants: $variantsinput,
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
                GQL;
          
            }


            
            // Initialize Guzzle client
            $client = new Client([
                'base_uri' => "https://$shop/admin/api/2024-10/",
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

            print_r($responseData);
            exit;
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
                    
                    throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                   
                    throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productCreate']['userErrors'], true));

                } else {
                    // Print the created product details
                    $inventory_item_id = $responseData['data']['productVariantUpdate']['productVariant']['inventoryItem']['id'];
                    
                    $inventory_item_id = str_replace("gid://shopify/InventoryItem/","",$inventory_item_id);
                    $productreturndata['variants'][0]['inventory_item_id'] = $inventory_item_id;
                    //inventory_item_id
                   
                }
            } catch (\Exception $e) {
                // Handle Guzzle exceptions
                throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
            }
          
          return $productreturndata;
    }

    public function graphqlUpdateProduct($params,  $shop,$accessToken)
    {

      
      
       
       

        $productdata = $params['product'];
      
        $product['id'] = $productdata['id'];
        if (strpos($productdata['id'], 'gid://shopify/Product') !== true) {
            $product['id'] = "gid://shopify/Product/{$productdata['id']}";
        }
        
        if(isset($productdata['title'])){
            $product['title'] = $productdata['title'];
        }

        if(isset($productdata['body_html'])){
            $product['bodyHtml'] = $productdata['body_html'];
        }

        if(isset($productdata['product_type'])){
            $product['productType'] = $productdata['product_type'];
        }
        
        if(isset($productdata['vendor'])){
            $product['vendor'] = $productdata['vendor'];
        }

        if(isset($productdata['tags'])){
            $product['tags'] = $productdata['tags'];
        }

        
        if(isset($productdata['template_suffix'])){
            $product['templateSuffix'] = $productdata['template_suffix'];
        }

        if(isset($productdata['status'])){
            $product['status'] = strtoupper($productdata['status']);
        }


        if(isset($productdata['published'])){
            $product['published'] = $productdata['published'];
        }
        
       
       
      
       
        if(!empty($productdata['metafields'])){
            foreach ($productdata['metafields'] as $metafieldkey => $metafield)
            {
                if (is_integer($metafield['value']))
                {
                    $productdata['metafields'][$metafieldkey]['value'] = (string)$metafield['value'];
                }

                if (strpos($metafield['id'], 'gid://shopify/Metafield') !== true) 
                {
                    $productdata['metafields'][$metafieldkey]['id'] = "gid://shopify/Metafield/".$metafield['id'];
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
        if(isset($productdata['status'])){
            $input = str_replace('"DRAFT"', "DRAFT", $input);
            $input = str_replace('"ACTIVE"', "ACTIVE", $input);
            $input = str_replace('"ARCHIVED"', "ARCHIVED", $input);
          
            //$product['status'] = strtoupper($productdata['status']);
        }
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

           $response = $client->post('graphql.json', [
            'body' => json_encode(['query' => $productquery])
        ]);

        // Get the response body
    
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
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['productUpdate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                       
                       throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productCreate']['userErrors'], true));

                    } else {
                        // Print the created product details
                        $variantid = $responseData['data']['productUpdate']['product']['variants']['edges'][0]['node']['id'];
                     

                        $productId = $responseData['data']['productUpdate']['product']['id'];

                        $productreturndata['id'] = str_replace("gid://shopify/Product/","", $productId);
                        $productreturndata['variants'][0]['id'] = str_replace("gid://shopify/ProductVariant/","", $variantid);

                    }
                } catch (\Exception $e) {
                    
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
           
            
            if(isset($productdata['variants']) && isset($productdata['variants']['price'])){
                
                $variant = $productdata['variants'][0];
                
               

                $variantdata['id'] = $variantid;
                $variantdata['price'] = $variant['price'];
                if(!empty($variant['compare_at_price'])){
                    $variantdata['compareAtPrice'] = $variant['compare_at_price'];
                }
                $variantdata['barcode'] = $variant['barcode'];
                $variantdata['sku'] = $variant['sku'];
                $variantdata['taxable'] = isset($variant['taxable']) ? $variant['taxable'] :  true;
                $variantdata['weight'] = (float)$variant['weight'];
                // $variantdata['inventoryQuantities'][0]['availableQuantity'] = 1245;
                // $variantdata['inventoryQuantities'][0]['locationId'] = "gid://shopify/Location/60977545293";
                // $variantdata['fulfillmentServiceId'] = 'gid://shopify/FulfillmentService/manual';
                $variantdata['inventoryManagement'] = 'SHOPIFY';

                //fix inventtor
                if(!empty($variant['cost'])){
                    $variantdata['inventoryItem']['cost'] =  $variant['cost'];
                    $variantdata['inventoryItem']['tracked'] = true;
                }
              
        
            
             
                $productvariants = json_encode($variantdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
                $productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
                $productvariants = str_replace('inventoryManagement:"SHOPIFY"','inventoryManagement:SHOPIFY',$productvariants);
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
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));
                    } elseif (isset($responseData['data']['productCreate']['userErrors']) && !empty($responseData['data']['productCreate']['userErrors'])) {
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productCreate']['userErrors'], true));
                    } else {
                        // Print the created product details
                        $inventory_item_id = $responseData['data']['productVariantUpdate']['productVariant']['inventoryItem']['id'];
                        
                        $inventory_item_id = str_replace("gid://shopify/InventoryItem/","",$inventory_item_id);
                        $productreturndata['variants'][0]['inventory_item_id'] = $inventory_item_id;
                        //inventory_item_id
                       
                    }
                } catch (\Exception $e) {
                    // Handle Guzzle exceptions
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }
            }
           
          
          return $productreturndata;
    }

    public function graphqlGetProducts($params, $shop,$accessToken){

       
        //dd($params);
        $gqparams = "";
        if(isset($params['created_at_max'])){
            $gqparams .= " created_at:<='{$params['created_at_max']}T00:00:00Z'";
        }

        if(isset($params['created_at_min'])){
            $gqparams .= " created_at:>='{$params['created_at_min']}T00:00:00Z'";
        }


        if(isset($params['published_status'])){
            if($params['published_status'] == 'published'){
                $gqparams .= " status:ACTIVE";    
            }elseif($params['published_status'] == 'unpublished'){
                $gqparams .= " status:DRAFT";
            }
            
        }
        if(isset($params['title'])){
            $gqparams .= " title:*{$params['title']}*";
        }

        if(isset($params['sku'])){
            $gqparams .= " sku:*{$params['sku']}*";
        }


        if(isset($params['handle'])){
            $gqparams .= " handle:*{$params['handle']}*";
        }

        
        if(isset($params['vendor'])){
            $gqparams .= " vendor:{$params['vendor']}";
        }

        if(isset($params['product_type'])){
            $gqparams .= " product_type:{$params['product_type']}";
        }
        
        if(isset($params['since_id']) && !empty($params['since_id'])){
            $gqparams .= " id:>{$params['since_id']}";
        }


        $cursor = '';
        if(isset($params['page_info']) && isset($params['direction']) && $params['direction'] == 'next'){
            // $gqparams .= " product_type:{$params['product_type']}";
            $cursor = 'after: "'.$params['page_info'].'"';
        }

        
        if(isset($params['page_info']) && isset($params['direction']) && $params['direction'] == 'previous' ){
            // $gqparams .= " product_type:{$params['product_type']}";
            $cursor = 'before: "'.$params['page_info'].'"';
        }

        
        
        
        if(!isset($params['limit'])){
            $params['limit'] = 25;
        }
        
        if(isset($params['ids']) && !empty($params['ids'])){
            $params['limit'] = 250;
            $productids = explode(",",$params['ids']);
            $productIdQuery = array();
            foreach($productids as $productid){
                $productIdQuery[] = "(id:{$productid})";
            }
            $productIdQuery = implode(" OR ",$productIdQuery);
          
            $gqparams .= ' '.$productIdQuery;
        }

       
       
        $query = array();
        $productvariants = json_encode($gqparams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
        //$productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
        $queryinput = $productvariants;
        if(isset($params['reverse'])){
            $reverseflag= "reverse: {$params['reverse']}";
        } else {
            $reverseflag = "";
        }
        //echo $queryinput;
        if(empty($gqparams)){
            $gqquery = "products(first: {$params['limit']},   $reverseflag, $cursor)";
        }else{
            $gqquery =  "products(first: {$params['limit']},  $reverseflag, query: $queryinput,$cursor)";
        }
        if(isset($params['direction']) && $params['direction'] == 'previous'){
            $gqquery = str_replace('first',"last",$gqquery);
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
        
        if(isset($params['fields'])){
            $parmsfields = explode(",",$params['fields']);
           
            if(in_array('variants',$parmsfields)){
                $fields['variants'] = 'variants(first: 250) {
                    edges {
                        node {
                        id
                        sku
                        title
                        price
                        weight
                        weightUnit
                        compareAtPrice
                        taxable
                        inventoryQuantity
                        inventoryPolicy
                        createdAt
                        selectedOptions {
                                name
                                value
                            }
                        fulfillmentService {
                                id
                                serviceName
                                type
                            }
                        inventoryItem {
                                id
                                inventoryHistoryUrl
                                unitCost {
                                    amount
                                    currencyCode
                                }
                            }
                        }
                    }
                }';
            }
            if(in_array('tags',$parmsfields)){
                $fields['tags'] = 'tags';
            }

            

          
        }
       $fields['createdAt'] = 'createdAt';
       $fields['publishedAt'] = 'publishedAt';
       $fields = implode("\n",$fields);
      
     
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
        if(isset($params['collection_id']) && !empty($params['collection_id'])){
        
            $collection_id = $params['collection_id'];
             
            $collection_handle = $this->getCollectionHandle($collection_id,$shop,$accessToken);
           
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
         
         
        $client = new Client([
            'base_uri' => "https://$shop/admin/api/2024-04/",
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken
            ]
        ]);
        
        if(0){
            $response = $client->post('graphql.json', [
                'body' => json_encode(['query' => $query])
            ]);

            // Get the response body
            $body = $response->getBody();
            $responseData = json_decode($body, true);
            dd($responseData);
        }

        $shopifyproducts = array();
        $pageinfo = array();
        try {
            // Send GraphQL request
            $response = $client->post('graphql.json', [
                'body' => json_encode(['query' => $query])
            ]);

            // Get the response body
            $body = $response->getBody();
            $responseData = json_decode($body, true);
           
            

            if(isset($responseData['data'])){
                if(isset($params['collection_id']) && !empty($params['collection_id'])){
                    $responseData['data']['products']['edges'] =  $responseData['data']['collectionByHandle']['products']['edges'];
                }

                foreach ($responseData['data']['products']['edges'] as $key => $product) {
                    $product = $product['node'];
                    $shopifyproduct = $product;
                    $shopifyproduct['id'] = str_replace("gid://shopify/Product/","", $shopifyproduct['id']);
                    $shopifyproduct['title'] = $product['title'];
                    $shopifyproduct['handle'] = $product['handle'];
                    $shopifyproduct['product_type'] = $product['productType'];
                    
                    
                    if(!empty($product['featuredImage'])){
                        $shopifyproduct['image']['src'] = $product['featuredImage']['url'];
                    }else{
                        $shopifyproduct['image'] = null;
                    }
                    if(!empty($product['publishedAt'])){
                        $shopifyproduct['published_at'] = $product['publishedAt'];
                    }else{
                        $shopifyproduct['published_at'] = NUll;
                    }
                    
                    if(!empty($product['variants'])){
                        $variants = array();
                        if(!empty($product['variants'])){
                          
                            foreach($product['variants']['edges'] as $qlvariant){
                                
                               
                                $variant = $qlvariant['node'];
                                $variant['product_id'] = $shopifyproduct['id'];
                                $variant['id'] = str_replace("gid://shopify/ProductVariant/","", $variant['id']);
                                $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/","", $variant['inventoryItem']['id']);
                                if(!empty($variant['compareAtPrice'])){
                                    $variant['compare_at_price'] = $variant['compareAtPrice'];
                                }
                                if(!empty($variant['weightUnit'])){
                                    $variant['weight_unit'] = $variant['weightUnit'];
                                }
                                if(!empty($variant['inventoryQuantity'])){
                                    $variant['inventory_quantity'] = $variant['inventoryQuantity'];
                                }else{
                                    $variant['inventory_quantity'] = 0;
                                }
                                if(isset($variant['inventoryItem']['unitCost']['amount'])){
                                    $variant['cost_price'] = $variant['inventoryItem']['unitCost']['amount'];
                                }else{
                                    $variant['cost_price'] = null;
                                }
                               
                                $variant['fulfillment_service']  = strtolower($variant['fulfillmentService']['serviceName']);
                                $variant['inventory_policy'] = strtolower($variant['inventoryPolicy']);
                                if(isset($variant['createdAt'])){
                                    $variant['created_at'] = $variant['createdAt'];
                                }

                                if(isset($variant['selectedOptions'])){
                                    foreach ($variant['selectedOptions'] as $selectedOptionskey => $selectedOption) {
                                        $optionkey = $selectedOptionskey+1;
                                        $variant["option{$optionkey}"] = $selectedOption['value'];
                                    }
                                    
                                }
                               $variants[] = $variant;
                            }
                        }
                        $shopifyproduct['variants'] = $variants;
                     
                    }
                    
                    if(!empty($product['tags'])){
                        $shopifyproduct['tags'] = implode(",",$product['tags']);
                    }
                   
                    if(isset($product['createdAt'])){
                        $shopifyproduct['created_at'] = $product['createdAt'];
                    }
                  
                    $shopifyproducts[$key] = $shopifyproduct;
                    
                }
    
                
                
               

                $responsedata['products'] = $shopifyproducts;
                if(isset($responseData['data']['products']['pageInfo'])){
                    $pageinfo = $responseData['data']['products']['pageInfo'];
                    $responsedata['pageinfo'] = $pageinfo;
                }
                return $responsedata;
            }

            
          
           
          
            if (isset($responseData["errors"])) {
                throw new \Exception('GraphQL Errors: ' . print_r($responseData["errors"], true));
            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new \Exception('Request Error: ' . print_r($e->getMessage(), true));
           
        }
    }

    public function graphqlGetProductsCount($params, $shop,$accessToken){


        //dd($params);
        $gqparams = "";
        if(isset($params['created_at_max'])){
            $gqparams .= " created_at:<='{$params['created_at_max']}T00:00:00Z'";
        }

        if(isset($params['created_at_min'])){
            $gqparams .= " created_at:>='{$params['created_at_min']}T00:00:00Z'";
        }


        if(isset($params['published_status'])){
            if($params['published_status'] == 'published'){
                $gqparams .= " status:ACTIVE";    
            }elseif($params['published_status'] == 'unpublished'){
                $gqparams .= " status:DRAFT";
            }
            
        }
        
        if(isset($params['vendor'])){
            $gqparams .= " vendor:{$params['vendor']}";
        }

        if(isset($params['product_type'])){
            $gqparams .= " product_type:{$params['product_type']}";
        }

        
        $cursor = '';
        if(isset($params['page_info']) && isset($params['direction']) && $params['direction'] == 'next'){
            // $gqparams .= " product_type:{$params['product_type']}";
            $cursor = 'after: "'.$params['page_info'].'"';
        }

        
        if(isset($params['page_info']) && isset($params['direction']) && $params['direction'] == 'previous' ){
            // $gqparams .= " product_type:{$params['product_type']}";
            $cursor = 'before: "'.$params['page_info'].'"';
        }
        
        if(!isset($params['limit'])){
            $params['limit'] = 25;
        }

        $query = array();
        $productvariants = json_encode($gqparams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $productvariants = preg_replace('/"([^"]+)"\s*:/','$1:', $productvariants);
        //$productvariants = str_replace('"MEDIAPMIMAGE"',"IMAGE",$productvariants);
        $queryinput = $productvariants;
        //echo $queryinput;
        if(empty($gqparams)){
            $gqquery = "products(first: {$params['limit']},$cursor)";
        }else{
            $gqquery =  "products(first: {$params['limit']}, query: $queryinput,$cursor)";
        }
        if(isset($params['direction']) && $params['direction'] == 'previous'){
            $gqquery = str_replace('first',"last",$gqquery);
        }
       
       
        $fields['id'] = 'id';
        $fields['title'] = 'title';
        $fields['handle'] = 'handle';
        $fields['status'] = 'status';
        $fields['featuredImage'] = 'featuredImage {
                                id
                                url
                            }';


        if(isset($params['fields'])){
            $parmsfields = explode(",",$params['fields']);
         
            if(in_array('variants',$parmsfields)){
                $fields['variants'] = 'variants(first: 250) {
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
                }';
            }
            
        }
       
       $fields = implode("\n",$fields);
      
     
        $onlinepublication = [];
        $query = <<<QUERY
                query {
                    productsCount {
                        count
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


        $shopifyproducts = array();
        $pageinfo = array();
        try {
            // Send GraphQL request
            $response = $client->post('graphql.json', [
                'body' => json_encode(['query' => $query])
            ]);

            // Get the response body
            $body = $response->getBody();
            $responseData = json_decode($body, true);
         
           
            

            if(isset($responseData['data'])){
                $responsedata =  $responseData['data']['productsCount'];
                return $responsedata;
            }

            
          
           
          
            if (isset($responseData["errors"])) {
                throw new \Exception('GraphQL Errors: ' . print_r($responseData["errors"], true));
            }
        } catch (\Exception $e) {
            // Handle Guzzle exceptions
            throw new \Exception('Request Error: ' . print_r($e->getMessage(), true));
           
        }
    }

    
    public function graphqlGetProduct($shopifyid, $shop,$accessToken){
        

        $shopifyid = "gid://shopify/Product/{$shopifyid}";
      
        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                title
                handle
                bodyHtml
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
                        'body' => json_encode(['query' => $query])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['product']['userErrors'], true));

                    }elseif (empty($responseData['data']['product'])) {
                        
                        throw new \Exception('GraphQL Error: Product Not Found');

                    } else {


                        $shopifyproduct = $responseData['data']['product'];
                        $shopifyproduct['id'] = str_replace("gid://shopify/Product/","", $shopifyproduct['id']);
                        
                        if(!empty($shopifyproduct['featuredImage'])){
                            $shopifyproduct['image']['src'] = $shopifyproduct['featuredImage']['url'];
                        }
                        $variants = array();
                        if(!empty($shopifyproduct['variants'])){
                          
                            foreach($shopifyproduct['variants']['edges'] as $qlvariant){
                                
                                $variant = $qlvariant['node'];
                                $variant['id'] = str_replace("gid://shopify/ProductVariant/","", $variant['id']);
                                $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/","", $variant['inventoryItem']['id']);

                                if(isset($variant['selectedOptions'])){
                                    foreach ($variant['selectedOptions'] as $selectedOptionskey => $selectedOption) {
                                        $optionkey = $selectedOptionskey+1;
                                        $variant["option{$optionkey}"] = $selectedOption['value'];
                                    }
                                    
                                }

                               $variants[] = $variant;
                            }
                        }
                        $shopifyproduct['variants'] = $variants;

                        
                        if(!empty($shopifyproduct['images'])){
                            $shopifyimages = [];
                            foreach($shopifyproduct['images']['edges'] as $image){
                               
                               $shopifyimage['id'] =  str_replace("gid://shopify/ProductImage/","", $image['node']['id']); 
                               $shopifyimage['src'] = $image['node']['src'];
                               
                               $shopifyimages[] = $shopifyimage;
                              
                            }
                            $shopifyproduct['images'] = $shopifyimages;
                        }

                        
                       
                        return $shopifyproduct;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }
    public function graphqlGetProductWithoutInventory($shopifyid, $shop,$accessToken){
        

        $shopifyid = "gid://shopify/Product/{$shopifyid}";
      
        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                title
                handle
                bodyHtml
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
                        'body' => json_encode(['query' => $query])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                
                 
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['product']['userErrors'], true));

                    }elseif (empty($responseData['data']['product'])) {
                        
                        throw new \Exception('GraphQL Error: Product Not Found');

                    } else {


                        $shopifyproduct = $responseData['data']['product'];
                        $shopifyproduct['id'] = str_replace("gid://shopify/Product/","", $shopifyproduct['id']);
                        
                        if(!empty($shopifyproduct['featuredImage'])){
                            $shopifyproduct['image']['src'] = $shopifyproduct['featuredImage']['url'];
                        }
                        $variants = array();
                        if(!empty($shopifyproduct['variants'])){
                          
                            foreach($shopifyproduct['variants']['edges'] as $qlvariant){
                                
                                $variant = $qlvariant['node'];
                                $variant['id'] =  str_replace("gid://shopify/ProductVariant/","", $variant['id']);
                                if(isset($variant['selectedOptions'])){
                                    foreach ($variant['selectedOptions'] as $selectedOptionskey => $selectedOption) {
                                        $optionkey = $selectedOptionskey+1;
                                        $variant["option{$optionkey}"] = $selectedOption['value'];
                                    }
                                    
                                }
                               $variants[] = $variant;
                            }
                        }
                        


                        $shopifyproduct['variants'] = $variants;
                        
                        
                        if(!empty($shopifyproduct['images'])){
                            $shopifyimages = [];
                            foreach($shopifyproduct['images']['edges'] as $image){
                               
                               $shopifyimage['id'] =  str_replace("gid://shopify/ProductImage/","", $image['node']['id']); 
                               $shopifyimage['src'] = $image['node']['src'];
                               
                               $shopifyimages[] = $shopifyimage;
                              
                            }
                            $shopifyproduct['images'] = $shopifyimages;
                        }

                        if(!empty($shopifyproduct['options'])){
                            $productoptions = array();
                         
                            foreach($shopifyproduct['options'] as $productoption){
                               
                                $productoption['id'] =  str_replace("gid://shopify/ProductOption/","", $productoption['id']); 
                               $productoptions[] = $productoption;
                               
                             }
                             $shopifyproduct['options'] = $productoptions;
                        }
                        $shopifyproduct['id'] =  str_replace("gid://shopify/Product/","", $shopifyproduct['id']);

                          
                        return $shopifyproduct;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }

    public function graphqlDeleteProduct($shopifyid, $shop,$accessToken){
        

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
                        'body' => json_encode(['query' => $query])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                   
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['product']['userErrors'], true));

                    } else {
                        
                        return $responseData;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }
    public function graphqlDeleteVariant($shopifyid,$variantid, $shop,$accessToken){
        

      

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
                        'body' => json_encode(['query' => $query])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                    
                 
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['productVariantsBulkDelete']['userErrors']) && !empty($responseData['data']['productVariantsBulkDelete']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productVariantsBulkDelete']['userErrors'], true));

                    } else {
                        
                        return $responseData;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }

    

    public function graphqlGetProductVariants($shopifyid, $shop,$accessToken){
        

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
                        'body' => json_encode(['query' => $query])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                  
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['product']['userErrors'], true));

                    } else {
                        $shopifyproduct = $responseData['data']['product'];
                        $shopifyproduct['id'] = str_replace("gid://shopify/Product/","", $shopifyproduct['id']);
                        
                        if(!empty($shopifyproduct['featuredImage'])){
                            $shopifyproduct['image']['src'] = $shopifyproduct['featuredImage']['url'];
                        }
                        if(!empty($shopifyproduct['variants'])){
                            $variants = array();
                            foreach($shopifyproduct['variants']['edges'] as $qlvariant){
                                
                                $variant = $qlvariant['node'];
                                $variant['id'] = str_replace("gid://shopify/ProductVariant/","", $variant['id']);
                                $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/","", $variant['inventoryItem']['id']);
                                unset($variant['inventoryItem']);
                                $variants[] = $variant;
                            }
                        }
                      
                       
                        return $variants;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }
    public function graphqlGetVariant($variantid, $shop,$accessToken){
        

       

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
                    weight
                    weightUnit
                    barcode
                    sku
                    inventoryItem {
                        id
                        inventoryHistoryUrl
                    }
                }
            }
        QUERY;
            
       
  
        


            // Initialize Guzzle client
            $client = new Client([
                'base_uri' => "https://$shop/admin/api/2024-04/",
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $accessToken
                ]
            ]);

          
            if(1){
           
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
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['productVariant']['userErrors']) && !empty($responseData['data']['productVariant']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['productVariant']['userErrors'], true));

                    } else {
                        $shopifyvariant = $responseData['data']['productVariant'];

                        
                       
                        $variant = $shopifyvariant;
                       
                        $variant['id'] = str_replace("gid://shopify/ProductVariant/","", $variant['id']);
                        $variant['inventory_item_id'] = str_replace("gid://shopify/InventoryItem/","", $variant['inventoryItem']['id']);
                        unset($variant['inventoryItem']);
                        
                      
                       
                        return $variant;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }
    

    public function graphqlCheckProductOnShopify($shopifyid, $shop,$accessToken){
        

        $shopifyid = "gid://shopify/Product/{$shopifyid}";

        $query = <<<QUERY
        query publications {
            product(id: "$shopifyid") {
                id
                status
            }
        }
        QUERY;
            
       
  
        


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
                        'body' => json_encode(['query' => $query])
                    ]);

                    // Get the response body
                    $body = $response->getBody();
                    $responseData = json_decode($body, true);
                  
                   
                    // Check for GraphQL or user errors
                    if (isset($responseData['errors'])) {
                    

                        throw new \Exception('GraphQL Error: ' . print_r($responseData['errors'], true));

                    } elseif (isset($responseData['data']['product']['userErrors']) && !empty($responseData['data']['product']['userErrors'])) {
                        
                        throw new \Exception('GraphQL Error: ' . print_r($responseData['data']['product']['userErrors'], true));

                    } else {
                        $shopifyproduct = $responseData['data']['product'];

                        
                        
                      
                       
                        return $shopifyproduct;

                    }
                } catch (\Exception $e) {
                    throw new \Exception('GraphQL Error: ' . print_r($e->getMessage(), true));
                }

            }
    }

    public function getCollectionHandle($collection_id,$shop,$accessToken){
        
       
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



        $client = new Client([
        'base_uri' => "https://$shop/admin/api/2024-04/",
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

        if(isset($responseData['data']['collection']['id'])){
             return $responseData['data']['collection']['handle'];
        }else{
            throw new \Exception('GraphQL Errors: Collection Not Found');
        }
    }

    public function graphQLQuery($query,$shop,$accessToken){
        $query = <<<QUERY
                    $query
                QUERY;



        $client = new Client([
        'base_uri' => "https://$shop/admin/api/2024-04/",
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

    public function reOrderProductImages($params,$shop,$accessToken){
        
       

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

        
                      
        $client = new Client([
            'base_uri' => "https://$shop/admin/api/2024-04/",
            'headers' => [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $accessToken
            ]
        ]);


        $response = $client->post('graphql.json', [
            'body' => json_encode(['query' => $mediaquery])
        ]);

        // Get the response body
        $body = $response->getBody();
        $responseData = json_decode($body, true);
       
        $shopifyimages = array();
        foreach ($responseData['data']['product']['images']['edges'] as $key => $image) {
            $imageid = str_replace('gid://shopify/ProductImage/','',$image['node']['id']);
            $imagesrc = $image['node']['src'];

            $shopifyimages[$imagesrc] = $imageid;
        }

      

        $productmedia = array();
        foreach ($responseData['data']['product']['media']['edges'] as $key => $media) {
        
           $mediaid = str_replace('gid://shopify/MediaImage/','',$media['node']['id']);

            if(isset($media['node']['image']['id'])){
                 // $imageid = str_replace('gid://shopify/ImageSource/','',$media['node']['image']['id']); //$media['node']['image']['id'];
                 // $productmedia[$imageid] = $mediaid;
                $mediaurl = $media['node']['image']['src'];

                $tmpimageid = $shopifyimages[$mediaurl];
                $productmedia[$tmpimageid] = $mediaid;
            }
          
        }

       
 





       $productorder = array();
        foreach ($params['product']['images'] as $key => $image) {
            $productorder[$image['position']] = '{ id: "gid://shopify/MediaImage/'. $productmedia[$image['id']] .'", newPosition: "'. $image['position'] .'" }';
           
        }
          ksort($productorder);
       $productorder = implode(",",$productorder);
      

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

        
                      
        $client = new Client([
            'base_uri' => "https://$shop/admin/api/2024-04/",
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


    public function getProductIdFromVairant($variantid,$shop,$accessToken){
        
       


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



        $client = new Client([
            'base_uri' => "https://$shop/admin/api/2024-04/",
            'headers' => [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $accessToken
            ]
        ]);

        $response = $client->post('graphql.json', [
        'body' => json_encode(['query' => $query])
        ]);

       
        $body = $response->getBody();
        $responseData = json_decode($body, true);

        if(isset($responseData['data']['productVariant'])){
            $product['product_id'] = str_replace("gid://shopify/Product/","", $responseData['data']['productVariant']['product']['id']);
            return $product;
             
        }else{
            throw new \Exception('GraphQL Errors: productVariant Not Found');
        }
    }
   

}
