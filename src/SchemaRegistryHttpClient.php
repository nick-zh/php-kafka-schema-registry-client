<?php

namespace Jobcloud\KafkaSchemaRegistryClient;

use Exception;
use Jobcloud\KafkaSchemaRegistryClient\Interfaces\SchemaRegistryClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SchemaRegistryHttpClient implements SchemaRegistryClientInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;
    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @param ClientInterface $client
     * @param RequestFactoryInterface $requestFactory
     * @param string $baseUrl
     * @param string $username
     * @param string $password
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        string $baseUrl,
        string $username,
        string $password
    )
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->password = $password;
        $this->requestFactory = $requestFactory;
    }

    /**
     * @param ResponseInterface $response
     * @return array
     */
    protected function parseJsonResponse(ResponseInterface $response): array
    {
        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $body
     * @param array  $queryParams
     * @return RequestInterface
     */
    protected function createRequest(
        string $method,
        string $uri,
        ?array $body = [],
        array $queryParams = []
    ): RequestInterface {

        $queryString = 0 !== count($queryParams) ? '?' . http_build_query($queryParams) : null;

        // Ensures that there is no trailing slashes or double slashes in endpoint URL
        $url = trim($this->baseUrl, '/') . '/' . trim($uri, '/') . $queryString;

        $request = $this->requestFactory->createRequest($method, $url);

        if (null !== $body && [] !== $body) {
            $jsonData = json_encode($body, JSON_THROW_ON_ERROR);

            $request = $request->withAddedHeader('Content-Length', (string) strlen($jsonData));
            $request->getBody()->write($jsonData);
        }

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/vnd.schemaregistry.v1+json')
            ->withHeader(
                'Authorization',
                sprintf('Basic %s', base64_encode(sprintf('%s:%s', $this->username, $this->password)))
            );
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array|null $body
     * @param array $queryParams
     * @return array|null
     * @throws ClientExceptionInterface
     */
    public function call(string $method, string $uri, ?array $body = null, array $queryParams = []): ?array
    {
        try{
            $response = $this->client->sendRequest($this->createRequest($method, $uri, $body, $queryParams));

            echo (string) $response->getBody();

            return $this->parseJsonResponse($response);
        } catch (Exception $e) {
            if ($e->getCode() === 40403) {
                return null;
            }

            throw $e;
        }
    }
}