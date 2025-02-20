<?php

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/config.php';

use Thalia\ShopifyRestToGraphql\Endpoints\ScriptTagsEndPoints;

$shopifyStore = $config['shopify_store'];
$accessToken = $config['shopify_access_token'];

$pageParams1 = array(
    "script_tag" => array
    (
        "event" => "onload",
        "src" => 'https://pawanmore.com/js/main.js',
        "display_scope" => 'online_store'
    )
);

$scripTagGraphql = new ScriptTagsEndPoints($shopifyStore,$accessToken);
$graphqlQuery = $scripTagGraphql->scriptTagCreate($pageParams1);

echo "Generated GraphQL Query:\n";
print_r($graphqlQuery); 
