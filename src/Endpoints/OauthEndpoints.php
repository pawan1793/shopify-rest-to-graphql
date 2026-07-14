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
     * @deprecated Superseded by refreshOfflineAccessToken(). This method is not conformant with
     *             Shopify's refresh grant (it omits grant_type=refresh_token and posts a spurious
     *             access_token) and never worked for the expiring-token flow. Use
     *             refreshOfflineAccessToken() instead. Retained only for backward compatibility.
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

    /**
     * Exchange an authorization code for an EXPIRING offline access token.
     *
     * Authorization-code grant with expiring=1. Unlike getAccessToken() (which returns only the
     * bare access_token string for the legacy non-expiring flow), this returns the full response
     * so the host can persist the refresh token and expiries.
     *
     * POST https://{shop}.myshopify.com/admin/oauth/access_token
     *
     * @param string $authorizationCode The OAuth authorization code from the callback.
     * @return array{access_token:string, expires_in:int, refresh_token:string, refresh_token_expires_in:int, scope:string}
     * @throws GraphqlException
     */
    public function getExpiringAccessToken(string $authorizationCode): array
    {
        return $this->postOauthToken([
            'client_id'     => $this->appApiKey,
            'client_secret' => $this->appSecret,
            'code'          => $authorizationCode,
            'expiring'      => 1,
        ]);
    }

    /**
     * Rotate an EXPIRING offline access token using its refresh token.
     *
     * Conformant refresh grant (grant_type=refresh_token). Shopify returns a NEW access token AND
     * a NEW refresh token; the previous refresh token is invalidated immediately, while the
     * previous access token stays valid until its own expiry. Persist the new tokens before use.
     *
     * POST https://{shop}.myshopify.com/admin/oauth/access_token
     *
     * @param string $refreshToken The current shprt_… refresh token.
     * @param string|null $clientId Defaults to the app API key passed to the constructor.
     * @param string|null $clientSecret Defaults to the app secret passed to the constructor.
     * @return array{access_token:string, expires_in:int, refresh_token:string, refresh_token_expires_in:int, scope:string}
     * @throws GraphqlException code 401 = refresh token invalid/expired (relaunch app);
     *                          429/5xx = transient (safe to retry with backoff for up to ~1h).
     */
    public function refreshOfflineAccessToken(string $refreshToken, ?string $clientId = null, ?string $clientSecret = null): array
    {
        return $this->postOauthToken([
            'client_id'     => $clientId ?? $this->appApiKey,
            'client_secret' => $clientSecret ?? $this->appSecret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Migrate an existing NON-EXPIRING offline token to an expiring one via token exchange.
     *
     * On success the original non-expiring token is REVOKED — this is irreversible. Persist the
     * returned tokens before relying on them. Run once per shop (background job or next launch).
     *
     * POST https://{shop}.myshopify.com/admin/oauth/access_token
     *
     * @param string $nonExpiringToken The existing non-expiring offline access token.
     * @param string|null $clientId Defaults to the app API key passed to the constructor.
     * @param string|null $clientSecret Defaults to the app secret passed to the constructor.
     * @return array{access_token:string, expires_in:int, refresh_token:string, refresh_token_expires_in:int, scope:string}
     * @throws GraphqlException
     */
    public function migrateToExpiringToken(string $nonExpiringToken, ?string $clientId = null, ?string $clientSecret = null): array
    {
        return $this->postOauthToken([
            'client_id'            => $clientId ?? $this->appApiKey,
            'client_secret'        => $clientSecret ?? $this->appSecret,
            'grant_type'           => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'subject_token'        => $nonExpiringToken,
            'subject_token_type'   => 'urn:shopify:params:oauth:token-type:offline-access-token',
            'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
            'expiring'             => 1,
        ]);
    }

    /**
     * Normalize a Shopify token response into persistable fields.
     *
     * Shopify returns relative lifetimes (expires_in / refresh_token_expires_in); consumers store
     * absolute UNIX timestamps (expires_at / refresh_token_expires_at). Pure helper so every
     * consumer computes them identically. Pass $now to make the result deterministic in tests.
     *
     * @param array $tokenResponse A response from getExpiringAccessToken/refreshOfflineAccessToken/migrateToExpiringToken.
     * @param int|null $now Reference epoch seconds; defaults to time().
     * @return array{access_token:?string, refresh_token:?string, scope:?string, expires_at:?int, refresh_token_expires_at:?int}
     */
    public static function toStorage(array $tokenResponse, ?int $now = null): array
    {
        $now = $now ?? time();

        return [
            'access_token'             => $tokenResponse['access_token'] ?? null,
            'refresh_token'            => $tokenResponse['refresh_token'] ?? null,
            'scope'                    => $tokenResponse['scope'] ?? null,
            'expires_at'               => isset($tokenResponse['expires_in'])
                ? $now + (int) $tokenResponse['expires_in'] : null,
            'refresh_token_expires_at' => isset($tokenResponse['refresh_token_expires_in'])
                ? $now + (int) $tokenResponse['refresh_token_expires_in'] : null,
        ];
    }

    /**
     * POST form-encoded credentials to the Shopify OAuth token endpoint.
     *
     * Credentials are sent in the request BODY (not the URL query string). The thrown
     * GraphqlException preserves the upstream HTTP status code so callers can apply Shopify's
     * retry rules: retry on 429/5xx, treat 401 as definitive.
     *
     * @param array $formParams Form fields to POST.
     * @return array Decoded JSON response.
     * @throws GraphqlException
     */
    private function postOauthToken(array $formParams): array
    {
        $url = "https://{$this->shopDomain}/admin/oauth/access_token";

        try {
            $response = $this->createClient()->post($url, ['form_params' => $formParams]);
        } catch (RequestException $e) {
            $status = $e->hasResponse()
                ? $e->getResponse()->getStatusCode()
                : GraphqlException::CODE_SERVICE_UNAVAILABLE;
            $body   = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            $errors = json_decode($body, true) ?: [['message' => $e->getMessage()]];

            throw new GraphqlException(
                'Shopify OAuth token request failed: ' . $this->shopDomain,
                $status,
                (array) $errors,
                $e
            );
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (! is_array($decoded)) {
            throw new GraphqlException(
                'Invalid JSON from Shopify OAuth token endpoint: ' . $this->shopDomain,
                GraphqlException::CODE_BAD_REQUEST,
                []
            );
        }

        return $decoded;
    }

    /**
     * Build the Guzzle client used for OAuth token requests.
     *
     * Test seam: override in a subclass to inject a MockHandler and assert the built request
     * without hitting a live Shopify store.
     */
    protected function createClient(): Client
    {
        return new Client();
    }

}
