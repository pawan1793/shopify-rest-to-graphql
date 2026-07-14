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
        // Legacy non-expiring flow (still supported, returns a bare access_token string):
        //   $accessToken = $scripTagGraphql->getAccessToken($_GET['code']);

        // Expiring offline token flow — returns the full response with refresh token + expiries.
        $tokens  = $scripTagGraphql->getExpiringAccessToken($_GET['code']);

        // Map Shopify's relative lifetimes to absolute timestamps you can persist.
        // Store $storage keyed by $shopifyStore in your app's DB (add columns:
        // access_token, expires_at, refresh_token, refresh_token_expires_at).
        $storage = OauthEndpoints::toStorage($tokens);

        // Before using the token, refresh it proactively if it is near expiry (5-min skew).
        // Persist the NEW tokens BEFORE using them — the old refresh token dies immediately.
        if (isset($storage['expires_at']) && time() >= $storage['expires_at'] - 300) {
            $rotated = $scripTagGraphql->refreshOfflineAccessToken($storage['refresh_token']);
            $storage = OauthEndpoints::toStorage($rotated); // re-persist $storage here
        }

        // Now build the API client with the current access token:
        // $service = new \Thalia\ShopifyRestToGraphql\GraphqlService($shopifyStore, $storage['access_token']);
    } catch (\Exception $e) {
        print_r($e);
    }


    echo "<pre>";
    print_r($storage);
    exit;
}


$graphqlQuery = $scripTagGraphql->getAuthorizeUrl($appScope, $redirectUrl);

