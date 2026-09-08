<?php

namespace Thalia\ShopifyRestToGraphql\Endpoints;

use GuzzleHttp\Client;
use Thalia\ShopifyRestToGraphql\GraphqlException;
use Thalia\ShopifyRestToGraphql\GraphqlService;

/**
 * Shopify Bulk Operations (queries and mutations).
 *
 *   Graphql Reference : https://shopify.dev/docs/api/usage/bulk-operations/queries
 *                       https://shopify.dev/docs/api/usage/bulk-operations/imports
 *
 * Typical flow for a bulk query:
 *   $op = $bulk->runQuery($query);               // ['id' => 'gid://shopify/BulkOperation/…', 'status' => 'CREATED']
 *   … poll $bulk->get($op['id']) until status is COMPLETED / FAILED / CANCELED …
 *   $bulk->downloadToFile($op['url'], '/tmp/result.jsonl');
 *
 * Typical flow for a bulk mutation:
 *   $path = $bulk->stagedUploadJsonl('/tmp/variables.jsonl');   // uploads the JSONL, returns the stagedUploadPath
 *   $op = $bulk->runMutation($mutation, $path);
 *   … poll / download as above; each result line carries __lineNumber and the mutation payload …
 *
 * Every GraphQL call throws GraphqlException on transport errors (see GraphqlService) and on
 * userErrors (code 400, the userErrors array in getErrors()).
 */
class BulkOperationsEndpoints
{
    public const RESOURCE_BULK_MUTATION_VARIABLES = 'BULK_MUTATION_VARIABLES';

    public const OPERATION_FIELDS = 'id status errorCode type objectCount rootObjectCount fileSize url partialDataUrl createdAt completedAt';

    private GraphqlService $graphqlService;

    private string $shopDomain;

    private string $accessToken;

    private array $options;

    /**
     * @param array $options Extra Guzzle options for the GraphQL client (see GraphqlService).
     */
    public function __construct(?string $shopDomain = null, ?string $accessToken = null, array $options = [])
    {
        if ($shopDomain === null || $accessToken === null) {
            throw new \InvalidArgumentException('Shop domain and access token must be provided.');
        }

        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
        $this->options = $options;
        $this->graphqlService = new GraphqlService($this->shopDomain, $this->accessToken, $options);
    }

    /**
     * Start a bulk query. $query is the plain query document (no `mutation` wrapper);
     * it is embedded in a triple-quoted block string, so it may span lines.
     *
     * @return array{id: string, status: string} the created bulkOperation
     * @throws GraphqlException userErrors (e.g. OPERATION_IN_PROGRESS) → code 400, getErrors() = userErrors
     */
    public function runQuery(string $query, ?string $clientIdentifier = null): array
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-07/mutations/bulkOperationRunQuery
        */

        $mutation = <<<'GRAPHQL'
        mutation BulkOperationRunQuery($query: String!) {
            bulkOperationRunQuery(query: $query) {
                bulkOperation { id status }
                userErrors { field message code }
            }
        }
        GRAPHQL;

        $response = $this->graphqlService->graphqlQueryThalia($mutation, ['query' => $this->normalizeQuery($query)]);

        return $this->payload($response, 'bulkOperationRunQuery');
    }

    /**
     * Upload a JSONL file of mutation variables (one JSON object per line) through
     * stagedUploadsCreate and return the stagedUploadPath to pass to runMutation().
     *
     * @throws GraphqlException on userErrors or when the multipart upload is refused
     */
    public function stagedUploadJsonl(string $path, ?string $filename = null): string
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-07/mutations/stagedUploadsCreate
        */

        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException("JSONL file not readable: {$path}");
        }

        $mutation = <<<'GRAPHQL'
        mutation StagedUploadsCreate($input: [StagedUploadInput!]!) {
            stagedUploadsCreate(input: $input) {
                stagedTargets {
                    url
                    resourceUrl
                    parameters { name value }
                }
                userErrors { field message }
            }
        }
        GRAPHQL;

        $variables = ['input' => [[
            'resource' => self::RESOURCE_BULK_MUTATION_VARIABLES,
            'filename' => $filename ?: basename($path),
            'mimeType' => 'text/jsonl',
            'httpMethod' => 'POST',
        ]]];

        $response = $this->graphqlService->graphqlQueryThalia($mutation, $variables);
        $payload = $this->payload($response, 'stagedUploadsCreate');

        $target = $payload['stagedTargets'][0] ?? null;
        if (! $target || empty($target['url'])) {
            throw new GraphqlException('stagedUploadsCreate returned no staged target', 500, [['message' => 'no staged target']]);
        }

        $multipart = [];
        $stagedUploadPath = null;
        foreach ($target['parameters'] as $parameter) {
            $multipart[] = ['name' => $parameter['name'], 'contents' => $parameter['value']];
            if ($parameter['name'] === 'key') {
                $stagedUploadPath = $parameter['value'];
            }
        }
        // the file part must come last
        $multipart[] = ['name' => 'file', 'contents' => fopen($path, 'r'), 'filename' => basename($path)];

        if ($stagedUploadPath === null) {
            throw new GraphqlException('stagedUploadsCreate returned no key parameter', 500, [['message' => 'no key parameter']]);
        }

        try {
            $client = new Client(array_replace(GraphqlService::getDefaultOptions(), ['timeout' => 600], $this->options));
            $upload = $client->post($target['url'], ['multipart' => $multipart]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : GraphqlException::CODE_SERVER_ERROR;
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

            throw new GraphqlException('Staged upload failed', $code, [['message' => mb_substr($body, 0, 1000)]], $e);
        } catch (\Exception $e) {
            throw new GraphqlException('Staged upload failed', GraphqlException::CODE_SERVICE_UNAVAILABLE, [['message' => $e->getMessage()]], $e);
        }

        if ($upload->getStatusCode() >= 300) {
            throw new GraphqlException('Staged upload failed', $upload->getStatusCode(), [['message' => mb_substr((string) $upload->getBody(), 0, 1000)]]);
        }

        return $stagedUploadPath;
    }

    /**
     * Start a bulk mutation over an uploaded JSONL file. $mutation must be a single
     * mutation document declaring the variables each JSONL line provides.
     *
     * @return array{id: string, status: string}
     * @throws GraphqlException
     */
    public function runMutation(string $mutation, string $stagedUploadPath, ?string $clientIdentifier = null): array
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-07/mutations/bulkOperationRunMutation
        */

        $document = <<<'GRAPHQL'
        mutation BulkOperationRunMutation($mutation: String!, $stagedUploadPath: String!, $clientIdentifier: String) {
            bulkOperationRunMutation(mutation: $mutation, stagedUploadPath: $stagedUploadPath, clientIdentifier: $clientIdentifier) {
                bulkOperation { id status }
                userErrors { field message code }
            }
        }
        GRAPHQL;

        $response = $this->graphqlService->graphqlQueryThalia($document, [
            'mutation' => $mutation,
            'stagedUploadPath' => $stagedUploadPath,
            'clientIdentifier' => $clientIdentifier,
        ]);

        return $this->payload($response, 'bulkOperationRunMutation');
    }

    /**
     * One bulk operation by id (status, errorCode, objectCount, url, …), or null when unknown.
     *
     * @return array|null keys of self::OPERATION_FIELDS
     */
    public function get(string $id): ?array
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-07/queries/bulkOperation
        */

        $query = 'query BulkOperation($id: ID!) { bulkOperation(id: $id) { '.self::OPERATION_FIELDS.' } }';

        $response = $this->graphqlService->graphqlQueryThalia($query, ['id' => $id]);
        $this->assertNoErrors($response);

        return $response['data']['bulkOperation'] ?? null;
    }

    /**
     * The shop's current (most recent) bulk operation of a type, or null.
     *
     * @param string $type QUERY | MUTATION
     */
    public function current(string $type = 'QUERY'): ?array
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-07/queries/currentBulkOperation
        */

        $query = 'query CurrentBulkOperation($type: BulkOperationType) { currentBulkOperation(type: $type) { '.self::OPERATION_FIELDS.' } }';

        $response = $this->graphqlService->graphqlQueryThalia($query, ['type' => $type]);
        $this->assertNoErrors($response);

        return $response['data']['currentBulkOperation'] ?? null;
    }

    /**
     * Cancel a CREATED/RUNNING operation. Returns the operation (status CANCELING/CANCELED).
     *
     * @throws GraphqlException
     */
    public function cancel(string $id): array
    {
        /*
            Graphql Reference : https://shopify.dev/docs/api/admin-graphql/2026-07/mutations/bulkOperationCancel
        */

        $mutation = <<<'GRAPHQL'
        mutation BulkOperationCancel($id: ID!) {
            bulkOperationCancel(id: $id) {
                bulkOperation { id status }
                userErrors { field message }
            }
        }
        GRAPHQL;

        $response = $this->graphqlService->graphqlQueryThalia($mutation, ['id' => $id]);

        return $this->payload($response, 'bulkOperationCancel');
    }

    /**
     * Stream a result file (`url` / `partialDataUrl`) to disk. The result URL is a
     * signed, time-limited link; no access token is sent.
     *
     * @return int bytes written
     * @throws GraphqlException
     */
    public function downloadToFile(string $url, string $path, int $timeout = 600): int
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }

        try {
            $client = new Client(['timeout' => $timeout, 'connect_timeout' => 10]);
            $client->get($url, ['sink' => $path]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : GraphqlException::CODE_SERVER_ERROR;

            throw new GraphqlException('Bulk result download failed', $code, [['message' => $e->getMessage()]], $e);
        } catch (\Exception $e) {
            throw new GraphqlException('Bulk result download failed', GraphqlException::CODE_SERVICE_UNAVAILABLE, [['message' => $e->getMessage()]], $e);
        }

        return (int) filesize($path);
    }

    /**
     * Subscribe the shop to the `bulk_operations/finish` webhook (fires when any bulk
     * operation of this app ends). Returns the webhook subscription array.
     *
     * @throws GraphqlException
     */
    public function subscribeFinishWebhook(string $address): array
    {
        $webhooks = new WebhooksEndpoints($this->shopDomain, $this->accessToken);

        return $webhooks->webhookSubscriptionCreate(['webhook' => [
            'topic' => 'bulk_operations/finish',
            'address' => $address,
            'format' => 'json',
        ]]);
    }

    /**
     * Shopify rejects a bulk query that carries a `query` keyword or operation name;
     * the plain selection set is what goes in.
     */
    private function normalizeQuery(string $query): string
    {
        $trimmed = trim($query);
        if (preg_match('/^query\b[^{]*\{/i', $trimmed)) {
            $trimmed = substr($trimmed, strpos($trimmed, '{'));
        }

        return $trimmed;
    }

    /** @return array the mutation payload (bulkOperation / stagedTargets …) */
    private function payload(array $response, string $field): array
    {
        $this->assertNoErrors($response);

        $payload = $response['data'][$field] ?? null;
        if (! is_array($payload)) {
            throw new GraphqlException("Shopify returned no {$field} payload", 500, [['message' => 'empty payload']]);
        }

        if (! empty($payload['userErrors'])) {
            throw new GraphqlException('GraphQL Error: '.$this->shopDomain, 400, $payload['userErrors']);
        }

        return $payload['bulkOperation'] ?? $payload;
    }

    private function assertNoErrors(array $response): void
    {
        if (isset($response['errors']) && empty($response['data'])) {
            throw new GraphqlException('GraphQL Error: '.$this->shopDomain, 400, (array) $response['errors']);
        }
    }
}
