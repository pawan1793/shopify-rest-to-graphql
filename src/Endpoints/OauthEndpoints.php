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
    
    public function __construct(?string $shopDomain = null, ?string $appApiKey = null, ?string $appSecret = null)
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

    /**
     * Request new access tokens using a refresh token (e.g. after rotating the client secret).
     *
     * POST https://{store}.myshopify.com/admin/oauth/access_token
     *
     * @param string $refreshToken The refresh token from your app in the Partner/Dev Dashboard.
     * @param string $accessToken The access token to refresh.
     * @param string|null $clientSecret The app client secret (defaults to the instance secret; pass the new secret when rotating).
     * @return array Decoded JSON response (typically includes access_token, and may include refresh_token, scope, expires_in).
     */
    public function refreshAccessToken(string $refreshToken, string $accessToken,?string $clientId = null, ?string $clientSecret = null): array
    {
        $url = "https://{$this->shopDomain}/admin/oauth/access_token";
       
        $client = new Client();

        try {
            $response = $client->post($url, [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'access_token' => $accessToken,
                ],
            ]);
            $decoded = json_decode($response->getBody(), true);
            if (! is_array($decoded)) {
                throw new GraphqlException('Invalid JSON from access token refresh: ' . $this->shopDomain, 400, []);
            }

            return $decoded;
        } catch (RequestException $e) {
            throw new GraphqlException($e->getMessage() . $this->shopDomain, 400, [], $e);
        }
    }

}
