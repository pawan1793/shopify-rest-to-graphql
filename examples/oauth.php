<?php

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/config.php';

use Thalia\ShopifyRestToGraphql\Endpoints\OauthEndpoints;

$shopifyStore = $config['shopify_store'];
$accessToken = $config['shopify_access_token'];
$appApiKey = $config['shopify_api_key'];
$appSecret = $config['shopify_secret'];
$appScope = 'read_themes,write_themes,read_script_tags, write_script_tags,read_products,write_products,read_inventory,write_inventory,read_orders,read_checkouts,read_publications,write_publications,read_locations';
$redirectUrl ="http://localhost/shopify-rest-to-graphql/examples/oauth.php";




$scripTagGraphql = new OauthEndpoints($shopifyStore,$appApiKey,$appSecret);
if(isset($_GET['code'])){

    try {
        $graphqlQuery = $scripTagGraphql->getAccessToken($_GET['code']);
    } catch (\Exception $e) {
        print_r($e);
    }
   

    echo "<pre>";
    print_r($graphqlQuery);
    exit;    
}


$graphqlQuery = $scripTagGraphql->getAuthorizeUrl($appScope, $redirectUrl);

