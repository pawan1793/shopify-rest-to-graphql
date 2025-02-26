<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;
use Thalia\ShopifyRestToGraphql\GraphqlException;
use GuzzleHttp\Exception\RequestException;

use GuzzleHttp\Client;
class OauthEndpoints
{

    private $graphqlService;

    private $shopDomain;
    private $appApiKey;
    private $appSecret;
    
    public function __construct(string $shopDomain = null, string $appApiKey = null,string $appSecret = null)
    {
        $this->shopDomain = $shopDomain;
        $this->appApiKey = $appApiKey;
        $this->appSecret = $appSecret;
    }


    public function getAuthorizeUrl($appScope, $redirectUrl) {

        $url = "https://{$this->shopDomain}/admin/oauth/authorize?client_id={$this->appApiKey}&scope=" . urlencode($appScope);
        if ($redirectUrl != '')
        {
            $url .= "&redirect_uri=" . urlencode($redirectUrl);
        }
        return $url;
    }

    public function getAccessToken($authorizationCode) {
        
        $url = "https://{$this->shopDomain}/admin/oauth/access_token?client_id={$this->appApiKey}&client_secret={$this->appSecret}&code=$authorizationCode";
        
        $client = new Client();

        try {
            $response = $client->post($url);
            $response = json_decode($response->getBody(), true);
            if (isset($response['access_token']))
                return $response['access_token'];
        } catch (RequestException $e) {
            throw new GraphqlException($e->getMessage() . $this->shopDomain, 400, [],$e);
        }

    }

    
}
