<?php

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/config.php';

use Thalia\ShopifyRestToGraphql\Endpoints\ThemesEndpoints;

$shopifyStore = '';
$accessToken = '';

$params['theme_id'] = 130676457610;
$scripTagGraphql = new ThemesEndpoints($shopifyStore,$accessToken);
$graphqlQuery = $scripTagGraphql->themesFiles($params);


echo "<pre>";
echo "Generated GraphQL Query:\n";
print_r($graphqlQuery); 
